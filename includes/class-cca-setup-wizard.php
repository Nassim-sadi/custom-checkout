<?php
defined( 'ABSPATH' ) || exit;

class CCA_Setup_Wizard {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
        add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'wp_ajax_cca_wizard_step', array( $this, 'ajax_step' ) );
    }
    public function register_page() {
        add_submenu_page( null, __( 'Configuration Custom Checkout', 'custom-checkout-algeria' ), __( 'Configuration Custom Checkout', 'custom-checkout-algeria' ), 'manage_woocommerce', 'cca-setup', array( $this, 'render' ) );
    }
    public function maybe_redirect() {
        if ( ! get_option('cca_show_wizard') ) return;
        if ( ! current_user_can('manage_woocommerce') ) return;
        if ( wp_doing_ajax() ) return;
        $page = isset($_GET['page']) ? sanitize_text_field( wp_unslash($_GET['page']) ) : '';
        if ( 'cca-setup' === $page ) return;
        delete_option('cca_show_wizard');
        wp_safe_redirect( admin_url('admin.php?page=cca-setup') );
        exit;
    }
    public function enqueue( $hook ) {
        if ( false === strpos( $hook, 'cca-setup' ) ) return;
        wp_enqueue_style( 'cca-admin', CCA_URL . 'assets/css/admin.css', array(), CCA_VERSION );
        wp_enqueue_script( 'cca-wizard', CCA_URL . 'assets/js/wizard.js', array('jquery'), CCA_VERSION, true );
        wp_localize_script( 'cca-wizard', 'ccaWizard', array(
            'ajaxUrl'=> admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('cca_wizard'),
            'settingsUrl'=> admin_url('admin.php?page=cca-settings'),
        ) );
    }
    public function render() {
        if ( ! current_user_can('manage_woocommerce') ) return;
        include CCA_PATH . 'templates/setup-wizard.php';
    }
    public function ajax_step() {
        check_ajax_referer('cca_wizard','nonce');
        if ( ! current_user_can('manage_woocommerce') ) wp_send_json_error( array('message'=> __( 'Permission refusée.', 'custom-checkout-algeria' )),403);
        $step = isset($_POST['step']) ? sanitize_text_field( wp_unslash($_POST['step']) ) : '';
        switch($step){
            case 'credentials': $this->handle_credentials(); break;
            case 'sender': $this->handle_sender(); break;
            case 'defaults': $this->handle_defaults(); break;
            case 'finish':
                update_option('cca_configured','1');
                delete_option('cca_show_wizard');
                wp_send_json_success( array('message'=> __( 'Configuration terminée.', 'custom-checkout-algeria' )));
                break;
            default: wp_send_json_error( array('message'=> __( 'Étape invalide.', 'custom-checkout-algeria' )));
        }
    }
    private function handle_credentials(){
        $api_id = isset($_POST['api_id']) ? sanitize_text_field( wp_unslash($_POST['api_id']) ) : '';
        $api_token = isset($_POST['api_token']) ? sanitize_text_field( wp_unslash($_POST['api_token']) ) : '';
        if ( empty($api_id) || empty($api_token) ) wp_send_json_error( array('message'=> __( 'Veuillez saisir l\'API ID et le Token.', 'custom-checkout-algeria' )));
        $res = CCA_API::test_credentials($api_id,$api_token);
        if ( empty($res['success']) ) wp_send_json_error( array('message'=> $res['error'] ?? __( 'Identifiants invalides.', 'custom-checkout-algeria' )));
        CCA_Settings::update( array('api_id'=>$api_id,'api_token'=>$api_token) );
        CCA_API::clear_cache();
        $wilayas = array();
        $data = $res['data'];
        $list = $data['data'] ?? $data;
        if ( is_array($list) ) {
            foreach($list as $w){
                if ( ! empty($w['is_deliverable']) || ! isset($w['is_deliverable']) ) {
                    if ( isset($w['id']) ) $wilayas[] = array('id'=>(int)$w['id'],'name'=>$w['name']??'');
                }
            }
        }
        wp_send_json_success( array('message'=> __( 'Connexion réussie.', 'custom-checkout-algeria' ), 'wilayas'=>$wilayas));
    }
    private function handle_sender(){
        $id = isset($_POST['from_wilaya_id']) ? absint($_POST['from_wilaya_id']) : 0;
        $name = isset($_POST['from_wilaya_name']) ? sanitize_text_field( wp_unslash($_POST['from_wilaya_name']) ) : '';
        if ( ! $id || '' === $name ) wp_send_json_error( array('message'=> __( 'Sélectionnez la wilaya d\'expédition.', 'custom-checkout-algeria' )));
        CCA_Settings::update( array('from_wilaya_id'=>$id,'from_wilaya_name'=>$name) );
        wp_send_json_success( array('message'=> __( 'Wilaya enregistrée.', 'custom-checkout-algeria' )));
    }
    private function handle_defaults(){
        CCA_Settings::update( array(
            'enable_home'=> ! empty($_POST['enable_home']) ? '1':'0',
            'enable_stopdesk'=> ! empty($_POST['enable_stopdesk']) ? '1':'0',
            'freeshipping'=> ! empty($_POST['freeshipping']) ? '1':'0',
            'do_insurance'=> ! empty($_POST['do_insurance']) ? '1':'0',
            'default_length'=> isset($_POST['default_length']) ? absint($_POST['default_length']) : 10,
            'default_width'=> isset($_POST['default_width']) ? absint($_POST['default_width']) : 10,
            'default_height'=> isset($_POST['default_height']) ? absint($_POST['default_height']) : 10,
            'default_weight'=> isset($_POST['default_weight']) ? max(0,floatval($_POST['default_weight'])) : 1,
        ));
        if ( empty($_POST['enable_home']) && empty($_POST['enable_stopdesk']) ) wp_send_json_error( array('message'=> __( 'Activez au moins un mode.', 'custom-checkout-algeria' )));
        wp_send_json_success( array('message'=> __( 'Options enregistrées.', 'custom-checkout-algeria' )));
    }
}
new CCA_Setup_Wizard();
