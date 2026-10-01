<?php defined( 'ABSPATH' ) || exit;

$data     = CCA_Admin_Links::read_readme();
$title    = $data['title'] ? $data['title'] : __( 'Readme', 'custom-checkout-algeria' );
$headers  = $data['headers'];
$sections = $data['sections'];
?>
<div class="wrap cca-readme-wrap">
    <h1><?php echo esc_html( $title ); ?></h1>

    <p class="cca-readme-meta">
        <?php
        $bits = array();
        if ( isset( $headers['Stable tag'] ) ) {
            $bits[] = esc_html__( 'Version', 'custom-checkout-algeria' ) . ' ' . esc_html( $headers['Stable tag'] );
        }
        if ( isset( $headers['Requires at least'] ) ) {
            $bits[] = esc_html__( 'WordPress', 'custom-checkout-algeria' ) . ' ' . esc_html( $headers['Requires at least'] );
        }
        if ( isset( $headers['Requires PHP'] ) ) {
            $bits[] = 'PHP ' . esc_html( $headers['Requires PHP'] );
        }
        if ( isset( $headers['WC requires at least'] ) ) {
            $bits[] = 'WooCommerce ' . esc_html( $headers['WC requires at least'] );
        }
        echo esc_html( implode( ' · ', $bits ) );
        ?>
    </p>

    <p class="cca-readme-actions">
        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cca-settings' ) ); ?>">
            <?php esc_html_e( 'Settings', 'custom-checkout-algeria' ); ?>
        </a>
        <a class="button" href="<?php echo esc_url( CCA_REPO_URL ); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e( 'Project page', 'custom-checkout-algeria' ); ?>
        </a>
        <a class="button" href="<?php echo esc_url( CCA_REPO_URL . '/releases' ); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e( 'Releases', 'custom-checkout-algeria' ); ?>
        </a>
    </p>

    <div class="cca-readme-body card">
        <?php if ( ! $sections ) : ?>
            <p><?php esc_html_e( 'No readme.txt was found in the plugin folder.', 'custom-checkout-algeria' ); ?></p>
        <?php else : ?>
            <?php foreach ( $sections as $section ) : ?>
                <?php if ( 2 === $section['level'] ) : ?>
                    <h2><?php echo esc_html( $section['title'] ); ?></h2>
                <?php else : ?>
                    <h3><?php echo esc_html( $section['title'] ); ?></h3>
                <?php endif; ?>
                <?php echo CCA_Admin_Links::render_lines( $section['lines'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
