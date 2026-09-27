<?php
defined( 'ABSPATH' ) || exit;

class CCA_Settings {

    const OPTION_KEY = 'cca_settings';

    public static function defaults() {
        return array(
            'api_id'           => '',
            'api_token'        => '',
            'from_wilaya_id'   => '',
            'from_wilaya_name' => '',
            'default_length'   => 10,
            'default_width'    => 10,
            'default_height'   => 10,
            'default_weight'   => 1,
            'enable_home'      => '1',
            'enable_stopdesk'  => '1',
            'freeshipping'     => '1',
            'do_insurance'     => '0',
        );
    }

    public static function all() {
        $stored = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $stored ) ) $stored = array();
        return wp_parse_args( $stored, self::defaults() );
    }

    public static function get( $key, $default = null ) {
        $all = self::all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
    }

    public static function update( $data ) {
        $current = self::all();
        $allowed = array_keys( self::defaults() );
        foreach ( $data as $k => $v ) {
            if ( in_array( $k, $allowed, true ) ) {
                // keep weight as float, rest sanitized
                if ( 'default_weight' === $k ) $current[$k] = max( 0, floatval( $v ) );
                elseif ( in_array( $k, array('default_length','default_width','default_height'), true ) ) $current[$k] = absint( $v ) ?: 10;
                elseif ( in_array( $k, array('enable_home','enable_stopdesk','freeshipping','do_insurance'), true ) ) $current[$k] = ! empty($v) ? '1':'0';
                else $current[$k] = is_string($v) ? sanitize_text_field($v) : $v;
            }
        }
        update_option( self::OPTION_KEY, $current );
        return $current;
    }

    public static function is_configured() {
        return (bool) ( self::get('api_id') && self::get('api_token') && self::get('from_wilaya_id') );
    }

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_save' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Custom Checkout – Yalidine', 'custom-checkout-algeria' ),
            __( 'Custom Checkout', 'custom-checkout-algeria' ),
            'manage_woocommerce',
            'cca-settings',
            array( $this, 'render' )
        );
    }

    public function enqueue( $hook ) {
        $load = ( false !== strpos( $hook, 'cca' ) || 'woocommerce_page_wc-orders' === $hook );
        if ( ! $load ) return;
        wp_enqueue_style( 'cca-admin', CCA_URL . 'assets/css/admin.css', array(), CCA_VERSION );
        wp_enqueue_script( 'cca-admin', CCA_URL . 'assets/js/admin.js', array('jquery'), CCA_VERSION, true );
        wp_localize_script( 'cca-admin', 'ccaAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('cca_admin'),
            'i18n'    => array(
                'sending' => __( 'Envoi en cours…', 'custom-checkout-algeria' ),
                'success' => __( 'Colis envoyé.', 'custom-checkout-algeria' ),
                'error'   => __( 'Erreur.', 'custom-checkout-algeria' ),
                'confirm' => __( 'Envoyer ce colis à Yalidine ?', 'custom-checkout-algeria' ),
            ),
        ) );
        // Settings page needs from_wilaya name sync
        if ( false !== strpos( $hook, 'cca-settings' ) ) {
            wp_enqueue_script( 'cca-settings', CCA_URL . 'assets/js/wizard.js', array('jquery'), CCA_VERSION, true );
        }
    }

    public function handle_save() {
        if ( ! isset( $_POST['cca_settings_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cca_settings_nonce'] ) ), 'cca_save_settings' ) ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        // Clear cache button
        if ( isset( $_POST['cca_clear_cache'] ) ) {
            CCA_API::clear_cache();
            add_settings_error( 'cca', 'cache_cleared', __( 'Cache vidé.', 'custom-checkout-algeria' ), 'updated' );
            return;
        }

        $data = isset($_POST['cca']) && is_array($_POST['cca']) ? wp_unslash($_POST['cca']) : array();
        $payload = array(
            'api_id'           => isset($data['api_id']) ? sanitize_text_field($data['api_id']) : '',
            'api_token'        => isset($data['api_token']) ? sanitize_text_field($data['api_token']) : '',
            'from_wilaya_id'   => isset($data['from_wilaya_id']) ? absint($data['from_wilaya_id']) : '',
            'from_wilaya_name' => isset($data['from_wilaya_name']) ? sanitize_text_field($data['from_wilaya_name']) : '',
            'enable_home'      => ! empty($data['enable_home']) ? '1':'0',
            'enable_stopdesk'  => ! empty($data['enable_stopdesk']) ? '1':'0',
            'freeshipping'     => ! empty($data['freeshipping']) ? '1':'0',
            'do_insurance'     => ! empty($data['do_insurance']) ? '1':'0',
            'default_length'   => isset($data['default_length']) ? absint($data['default_length']) : 10,
            'default_width'    => isset($data['default_width']) ? absint($data['default_width']) : 10,
            'default_height'   => isset($data['default_height']) ? absint($data['default_height']) : 10,
            'default_weight'   => isset($data['default_weight']) ? max(0,floatval($data['default_weight'])) : 1,
        );
        // If wilaya name empty but id set, try lookup
        if ( $payload['from_wilaya_id'] && empty($payload['from_wilaya_name']) ) {
            $api = CCA_API::get_wilayas();
            if ( ! empty($api['success']) && ! empty($api['data']['data']) ) {
                foreach($api['data']['data'] as $w){ if((int)$w['id']=== (int)$payload['from_wilaya_id']){ $payload['from_wilaya_name']=$w['name']; break; } }
                if ( empty($payload['from_wilaya_name']) && ! empty($api['data']) && isset($api['data']['data']) ) {} // fallback
            }
        }
        self::update($payload);
        CCA_API::clear_cache();
        // Validate credentials if both present – test wilayas
        if ( $payload['api_id'] && $payload['api_token'] ) {
            $test = CCA_API::test_credentials($payload['api_id'],$payload['api_token']);
            if ( empty($test['success']) ) {
                add_settings_error('cca','invalid_creds', ($test['error'] ?? __( 'Identifiants invalides.', 'custom-checkout-algeria' )), 'error');
            } else {
                update_option('cca_configured','1');
                add_settings_error('cca','saved', __( 'Paramètres enregistrés – connexion réussie.', 'custom-checkout-algeria' ), 'updated');
            }
        } else {
            add_settings_error('cca','saved', __( 'Paramètres enregistrés.', 'custom-checkout-algeria' ), 'updated');
        }
    }

    public function render() {
        if ( ! current_user_can('manage_woocommerce') ) return;
        $settings = self::all();
        $wilayas = array();
        $creds = CCA_API::get_credentials();
        if ( $creds['api_id'] && $creds['api_token'] ) {
            $res = CCA_API::get_wilayas();
            if ( ! empty($res['success']) ) {
                $data = $res['data'];
                // createk returns data.data, NS returns data directly. Handle both.
                $list = $data['data'] ?? $data;
                if ( is_array($list) ) {
                    foreach($list as $w){
                        if ( empty($w['is_deliverable']) && isset($w['is_deliverable']) ) continue;
                        if ( isset($w['id']) ) $wilayas[] = array('id'=>(int)$w['id'],'name'=>$w['name']??'');
                    }
                }
            }
        }
        settings_errors('cca');
        include CCA_PATH . 'templates/settings-page.php';
    }
}

new CCA_Settings();
