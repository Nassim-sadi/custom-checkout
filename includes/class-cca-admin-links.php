<?php
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'CCA_REPO_URL' ) ) {
    define( 'CCA_REPO_URL', 'https://github.com/Nassim-sadi/custom-checkout' );
}

/**
 * Adds readme/documentation links to the WordPress Plugins screen and renders
 * the bundled readme.txt in wp-admin.
 *
 * WordPress only surfaces a plugin's readme.txt on the Plugins screen when the
 * plugin was installed from the WordPress.org directory. A locally installed or
 * self-hosted plugin gets no readme at all, so we render readme.txt ourselves.
 */
class CCA_Admin_Links {

    public function __construct() {
        add_filter( 'plugin_action_links_' . plugin_basename( CCA_FILE ), array( $this, 'action_links' ) );
        add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
        add_action( 'admin_menu', array( $this, 'register_page' ) );
    }

    /**
     * Settings shortcut next to Activate / Deactivate.
     */
    public function action_links( $links ) {
        $own = array();

        if ( current_user_can( 'manage_woocommerce' ) ) {
            if ( ! CCA_Settings::is_configured() ) {
                $own['setup'] = sprintf(
                    '<a href="%s" class="cca-action-setup">%s</a>',
                    esc_url( admin_url( 'admin.php?page=cca-setup' ) ),
                    esc_html__( 'Setup', 'custom-checkout-algeria' )
                );
            }
            $own['settings'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( admin_url( 'admin.php?page=cca-settings' ) ),
                esc_html__( 'Settings', 'custom-checkout-algeria' )
            );
        }

        $own['readme'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=cca-readme' ) ),
            esc_html__( 'Readme', 'custom-checkout-algeria' )
        );

        return array_merge( $own, $links );
    }

    /**
     * Documentation links on the far-right column of the Plugins screen.
     */
    public function row_meta( $meta, $plugin_file ) {
        if ( plugin_basename( CCA_FILE ) !== $plugin_file ) {
            return $meta;
        }

        $links = array(
            sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url( CCA_REPO_URL ),
                esc_html__( 'GitHub', 'custom-checkout-algeria' )
            ),
            sprintf(
                '<a href="%s/releases" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url( CCA_REPO_URL ),
                esc_html__( 'Releases', 'custom-checkout-algeria' )
            ),
            sprintf(
                '<a href="%s/blob/main/CHANGELOG.md" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url( CCA_REPO_URL ),
                esc_html__( 'Changelog', 'custom-checkout-algeria' )
            ),
            sprintf(
                '<a href="%s">%s %s</a>',
                esc_url( admin_url( 'admin.php?page=cca-readme' ) ),
                esc_html__( 'Readme', 'custom-checkout-algeria' ),
                esc_html( CCA_VERSION )
            ),
        );

        return array_merge( $meta, $links );
    }

    public function register_page() {
        add_submenu_page(
            null,
            __( 'Custom Checkout – Readme', 'custom-checkout-algeria' ),
            __( 'Custom Checkout Readme', 'custom-checkout-algeria' ),
            'manage_woocommerce',
            'cca-readme',
            array( $this, 'render' )
        );
    }

    public function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        include CCA_PATH . 'templates/readme.php';
    }

    /**
     * Read readme.txt from disk.
     *
     * @return array Title, headers, and section list.
     */
    public static function read_readme() {
        $file = CCA_PATH . 'readme.txt';
        $raw  = is_readable( $file ) ? file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw  = str_replace( "\r\n", "\n", (string) $raw );

        $title    = '';
        $headers  = array();
        $sections = array();
        $index    = null;
        $in_header = true;

        foreach ( explode( "\n", $raw ) as $line ) {
            $line = rtrim( $line );

            // Plugin title: === Custom Checkout Algeria ===
            if ( preg_match( '/^===\s*(.+?)\s*===$/', $line, $m ) ) {
                $title     = $m[1];
                $in_header = true;
                continue;
            }

            // Section heading: == Description ==  /  = 1.2.0 =
            if ( preg_match( '/^(=+)\s*(.+?)\s*\1$/', $line, $m ) ) {
                $in_header = false;
                $sections[] = array(
                    'level' => ( strlen( $m[1] ) >= 2 ) ? 2 : 3,
                    'title' => $m[2],
                    'lines' => array(),
                );
                $index = count( $sections ) - 1;
                continue;
            }

            // The header block runs from the title until the first blank line;
            // everything after it is the short description or a section body.
            if ( $in_header ) {
                if ( '' === trim( $line ) ) {
                    $in_header = false;
                    continue;
                }
                if ( preg_match( '/^([A-Z][A-Za-z ]*):\s*(.+)$/', $line, $m ) ) {
                    $headers[ $m[1] ] = $m[2];
                    continue;
                }
                $in_header = false;
            }

            if ( null !== $index ) {
                $sections[ $index ]['lines'][] = $line;
            }
        }

        return array(
            'title'    => $title,
            'headers'  => $headers,
            'sections' => $sections,
        );
    }

    /**
     * Minimal readme.txt renderer: headings, lists, and inline
     * **bold**, `code` and [links](url).
     *
     * readme.txt wraps long lines, so an unindented line following a list item
     * is a continuation of that item rather than a new paragraph.
     */
    public static function render_lines( $lines ) {
        $out  = '';
        $mode = ''; // '', 'p', 'ul' or 'ol'
        $buf  = array();

        foreach ( $lines as $line ) {
            $t = trim( $line );

            if ( '' === $t ) {
                $out .= self::close_block( $mode, $buf );
                $mode = '';
                $buf  = array();
                continue;
            }

            $is_bullet = (bool) preg_match( '/^[*\-]\s+(.*)$/', $t, $m1 );
            $is_number = ! $is_bullet && (bool) preg_match( '/^\d+[.)]\s+(.*)$/', $t, $m2 );

            if ( $is_bullet || $is_number ) {
                $want = $is_bullet ? 'ul' : 'ol';

                // Switching list type, or leaving a list for another type.
                if ( 'p' === $mode ) {
                    $out  .= self::close_block( $mode, $buf );
                    $mode  = '';
                    $buf   = array();
                }
                if ( ( 'ul' === $mode || 'ol' === $mode ) && $want !== $mode ) {
                    $out .= self::close_block( $mode, $buf );
                    $mode = '';
                    $buf  = array();
                }
                if ( $want !== $mode ) {
                    $out  .= "<$want>\n";
                    $mode  = $want;
                    $buf   = array();
                }

                // Flush the item we were still accumulating.
                if ( $buf ) {
                    $out .= self::list_item( $buf );
                    $buf = array();
                }
                $buf = array( $is_bullet ? $m1[1] : $m2[1] );
                continue;
            }

            if ( '' === $mode ) {
                $mode = 'p';
            }
            $buf[] = $t;
        }

        $out .= self::close_block( $mode, $buf );

        return $out;
    }

    /**
     * Render the pending block and return the resulting markup.
     */
    private static function close_block( $mode, $buf ) {
        if ( ! $buf ) {
            return ( 'ul' === $mode || 'ol' === $mode ) ? "</$mode>\n" : '';
        }
        if ( 'p' === $mode ) {
            return '<p>' . self::inline( self::squash( $buf ) ) . "</p>\n";
        }
        return self::list_item( $buf ) . "</$mode>\n";
    }

    private static function list_item( $buf ) {
        $text = self::squash( $buf );
        return '' === $text ? '' : '<li>' . self::inline( $text ) . "</li>\n";
    }

    private static function squash( $buf ) {
        return trim( preg_replace( '/\s+/', ' ', implode( ' ', $buf ) ) );
    }

    /**
     * Escape, then apply readme.txt inline markup.
     */
    public static function inline( $text ) {
        $text = esc_html( $text );

        // `code`
        $text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
        // **bold**
        $text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
        // [label](url) - http/https/mailto only, so a bare "(not a link)" or a
        // dangerous scheme is left as literal text.
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(((?:https?:\/\/|mailto:)[^\s)]+)\)/',
            function ( $m ) {
                return sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url( $m[2] ),
                    $m[1]
                );
            },
            $text
        );

        return wp_kses( $text, array(
            'code'   => array(),
            'strong' => array(),
            'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
        ) );
    }
}

new CCA_Admin_Links();
