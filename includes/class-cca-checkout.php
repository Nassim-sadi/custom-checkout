<?php
defined( 'ABSPATH' ) || exit;

class CCA_Checkout {
    public function __construct() {
        add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_fields' ), 999 );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_fields' ) );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'map_address_names' ), 10, 2 );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin' ), 10, 1 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

        // Delivery type + stopdesk extra area injected via order review (like createk) OR keep as billing fields – we add hidden handling via JS.
        add_action( 'woocommerce_checkout_order_review', array( $this, 'render_delivery_fields' ), 15 );

        add_action( 'wp_ajax_cca_get_communes', array( $this, 'ajax_get_communes' ) );
        add_action( 'wp_ajax_nopriv_cca_get_communes', array( $this, 'ajax_get_communes' ) );
        add_action( 'wp_ajax_cca_get_centers', array( $this, 'ajax_get_centers' ) );
        add_action( 'wp_ajax_nopriv_cca_get_centers', array( $this, 'ajax_get_centers' ) );
        add_action( 'wp_ajax_cca_get_fee', array( $this, 'ajax_get_fee' ) );
        add_action( 'wp_ajax_nopriv_cca_get_fee', array( $this, 'ajax_get_fee' ) );

        add_action( 'woocommerce_checkout_update_order_review', array( $this, 'store_session_from_post' ) );
        add_filter( 'woocommerce_checkout_posted_data', array( $this, 'normalize_posted_data' ) );

        // Fees: add as cart fee + bust cache
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_fee' ), 20 );
        add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'bust_shipping_cache' ), 20 );
    }

    public function normalize_posted_data( $data ) {
        // Ensure phone with full name split if needed (like createk)
        if ( ! empty( $data['billing_full_name'] ) && empty( $data['billing_last_name'] ) ) {
            $parts = preg_split( '/\s+/', trim( $data['billing_full_name'] ), 2 );
            $data['billing_first_name'] = $parts[0];
            $data['billing_last_name'] = isset($parts[1]) && $parts[1]!=='' ? $parts[1] : '.';
        }
        return $data;
    }

    public function customize_fields( $fields ) {
        // Remove defaults
        unset( $fields['billing']['billing_company'] );
        unset( $fields['billing']['billing_address_1'] );
        unset( $fields['billing']['billing_address_2'] );
        unset( $fields['billing']['billing_city'] );
        unset( $fields['billing']['billing_postcode'] );
        unset( $fields['billing']['billing_country'] );
        // Keep billing_state but we repurpose as wilaya hidden id stored separately – hide it
        if ( isset($fields['billing']['billing_state']) ) {
            $fields['billing']['billing_state']['required']=false;
            $fields['billing']['billing_state']['class']=array('form-row-wide','cca-hidden-field');
            $fields['billing']['billing_state']['priority']=200;
        }
        unset( $fields['billing']['billing_first_name'] );
        unset( $fields['billing']['billing_last_name'] );
        unset( $fields['billing']['billing_email'] );

        $fields['billing']['billing_full_name'] = array(
            'label'       => 'Nom Complet',
            'placeholder' => 'Votre nom et prénom',
            'required'    => true,
            'class'       => array( 'form-row-wide' ),
            'clear'       => true,
            'priority'    => 10,
        );
        $fields['billing']['billing_phone']['priority']=20;
        $fields['billing']['billing_phone']['class']=array('form-row-wide');
        $fields['billing']['billing_phone']['label']='Numéro de téléphone';
        $fields['billing']['billing_phone']['placeholder']='0XXXXXXXXX';

        $fields['billing']['billing_wilaya'] = array(
            'type'        => 'select',
            'label'       => 'Wilaya',
            'required'    => true,
            'class'       => array( 'form-row-first', 'update_totals_on_change', 'cca-wilaya-field' ),
            'options'     => array( '' => 'Sélectionnez votre wilaya' ),
            'priority'    => 30,
        );
        $fields['billing']['billing_commune'] = array(
            'type'        => 'select',
            'label'       => 'Commune',
            'required'    => true,
            'class'       => array( 'form-row-last', 'cca-commune-field' ),
            'options'     => array( '' => 'Sélectionnez votre commune' ),
            'priority'    => 40,
        );
        $settings = CCA_Settings::all();
        $opts = array();
        if ( $settings['enable_home']==='1' ) $opts['home']='Livraison à domicile';
        if ( $settings['enable_stopdesk']==='1' ) $opts['desk']='Stop desk (Récupérer au bureau)';
        if ( empty($opts) ) $opts = array('home'=>'Livraison à domicile','desk'=>'Stop desk');
        $fields['billing']['billing_delivery_type'] = array(
            'type'    => 'radio',
            'label'   => 'Type de livraison',
            'required'=> true,
            'class'   => array( 'form-row-wide', 'custom-radio-group' ),
            'options' => $opts,
            'default' => isset($opts['home']) ? 'home' : 'desk',
            'priority'=> 50,
        );
        return $fields;
    }

    public function render_delivery_fields() {
        // Always render hidden fields so JS + session work even when not configured / fallback static.
        $settings = CCA_Settings::all();
        echo '<input type="hidden" name="cca_wilaya_id" id="cca_wilaya_id" value="" />';
        echo '<input type="hidden" name="cca_wilaya_name" id="cca_wilaya_name" value="" />';
        echo '<input type="hidden" name="cca_commune_id" id="cca_commune_id" value="" />';
        echo '<input type="hidden" name="cca_fee" id="cca_fee" value="" />';
        // Only render stopdesk box if enabled
        if ( $settings['enable_stopdesk'] !== '1' ) return;
        echo '<div id="cca-stopdesk-box" class="cca-stopdesk-box" style="display:none; margin:10px 0;">';
        echo '<h4>Choisir un stop desk <abbr class="required">*</abbr></h4>';
        echo '<div id="cca_stopdesk_list" class="cca-stopdesk-list"><p class="cca-stopdesk-empty">Sélectionnez une wilaya et une commune</p></div>';
        echo '<input type="hidden" name="cca_stopdesk_id" id="cca_stopdesk_id" value="" />';
        echo '<input type="hidden" name="cca_stopdesk_name" id="cca_stopdesk_name" value="" />';
        echo '</div>';
    }

    public function enqueue() {
        if ( ! is_checkout() || is_order_received_page() ) return;
        wp_enqueue_style( 'custom-checkout-style', CCA_URL . 'assets/css/checkout.css', array(), CCA_VERSION );
        // keep cities-data.js as fallback when API not configured
        wp_enqueue_script( 'cities-data', CCA_URL . 'assets/js/cities-data.js', array(), CCA_VERSION, true );
        wp_enqueue_script( 'custom-checkout-script', CCA_URL . 'assets/js/checkout.js', array('jquery','wc-checkout'), CCA_VERSION, true );

        $settings = CCA_Settings::all();
        // Build wilayas list if credentials present – use cached transient only, avoid live API on every page load.
        $wilayas = array();
        $creds = CCA_API::get_credentials();
        if ( $creds['api_id'] && $creds['api_token'] ) {
            // Try transient first; if missing, leave empty and let JS fetch via AJAX (avoids blocking TTFB).
            $cached = get_transient('cca_wilayas');
            if ( false !== $cached && ! empty($cached['success']) ) {
                $list = $cached['data']['data'] ?? $cached['data'];
                if ( is_array($list) ) foreach($list as $w){ if(isset($w['id'])) $wilayas[] = array('id'=>(int)$w['id'],'name'=>$w['name']); }
            } else {
                // No cache yet – attempt quick fetch but do not fail checkout if it errors.
                $res = CCA_API::get_wilayas();
                if ( ! empty($res['success']) ) {
                    $list = $res['data']['data'] ?? $res['data'];
                    if ( is_array($list) ) foreach($list as $w){ if(isset($w['id'])) $wilayas[] = array('id'=>(int)$w['id'],'name'=>$w['name']); }
                }
            }
        }
        // fallback to static if empty – JS will use cities-data
        wp_localize_script( 'custom-checkout-script', 'ccaCheckout', array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('cca_checkout'),
            'wilayas'        => $wilayas,
            'enableHome'     => $settings['enable_home']==='1',
            'enableStopdesk' => $settings['enable_stopdesk']==='1',
            // Lets the JS restore the customer's selection after a refresh,
            // so nobody has to re-pick wilaya → commune → stop desk.
            'restore'        => self::session_state(),
            'i18n'           => array(
                'selectWilaya'  => 'Sélectionnez une wilaya',
                'selectCommune' => 'Sélectionnez une commune',
                'selectCenter'  => 'Sélectionnez un stop desk',
                'noCenterInCommune' => 'Aucun stop desk dans cette commune.',
                'loading'       => 'Chargement…',
                'feeError'      => 'Impossible de calculer les frais.',
                'home'          => 'Livraison à domicile',
                'stopdesk'      => 'Stop desk',
            ),
        ) );
    }

    public function validate_fields() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $wilaya = isset($_POST['billing_wilaya']) ? sanitize_text_field( wp_unslash($_POST['billing_wilaya']) ) : '';
        $wilaya_id = isset($_POST['cca_wilaya_id']) ? absint($_POST['cca_wilaya_id']) : 0;
        // fallback: billing_wilaya value may be id
        if ( ! $wilaya_id && is_numeric($wilaya) ) $wilaya_id = absint($wilaya);
        $commune = isset($_POST['billing_commune']) ? sanitize_text_field( wp_unslash($_POST['billing_commune']) ) : '';
        $commune_id = isset($_POST['cca_commune_id']) ? absint($_POST['cca_commune_id']) : 0;
        $delivery = isset($_POST['billing_delivery_type']) ? sanitize_text_field( wp_unslash($_POST['billing_delivery_type']) ) : '';
        $stopdesk_id = isset($_POST['cca_stopdesk_id']) ? absint($_POST['cca_stopdesk_id']) : 0;
        $phone = isset($_POST['billing_phone']) ? sanitize_text_field( wp_unslash($_POST['billing_phone']) ) : '';
        // phpcs:enable
        $settings = CCA_Settings::all();
        if ( empty($wilaya) && ! $wilaya_id ) wc_add_notice( __( 'Veuillez sélectionner une wilaya.', 'custom-checkout-algeria' ), 'error' );
        if ( empty($commune) && ! $commune_id ) wc_add_notice( __( 'Veuillez sélectionner une commune.', 'custom-checkout-algeria' ), 'error' );
        if ( ! in_array($delivery, array('home','desk','stopdesk'), true) ) wc_add_notice( __( 'Veuillez choisir un mode de livraison.', 'custom-checkout-algeria' ), 'error' );
        $isDesk = in_array($delivery, array('desk','stopdesk'), true);
        if ( $isDesk && '1' !== $settings['enable_stopdesk'] ) wc_add_notice( __( 'Stop desk non activé.', 'custom-checkout-algeria' ), 'error' );
        if ( ! $isDesk && '1' !== $settings['enable_home'] ) wc_add_notice( __( 'Livraison à domicile non activée.', 'custom-checkout-algeria' ), 'error' );
        if ( $isDesk && ! $stopdesk_id ) wc_add_notice( __( 'Veuillez sélectionner un stop desk.', 'custom-checkout-algeria' ), 'error' );
        if ( $phone && ! preg_match('/^(05|06|07)[0-9]{8}$/', $phone) ) wc_add_notice( 'Le numéro de téléphone doit être un numéro algérien valide (ex: 05XXXXXXXX).', 'error' );
    }

    public function map_address_names( $order, $data ) {
        $wilaya_id = isset($data['cca_wilaya_id']) ? absint($data['cca_wilaya_id']) : ( isset($data['billing_wilaya']) && is_numeric($data['billing_wilaya']) ? absint($data['billing_wilaya']) : 0 );
        $wilaya_name = isset($data['cca_wilaya_name']) ? sanitize_text_field($data['cca_wilaya_name']) : '';
        if ( ! $wilaya_name && $wilaya_id ) $wilaya_name = $this->get_wilaya_name($wilaya_id);
        if ( ! $wilaya_name && ! empty($data['billing_wilaya']) ) $wilaya_name = sanitize_text_field($data['billing_wilaya']);
        $commune = isset($data['billing_commune']) ? sanitize_text_field($data['billing_commune']) : ( $data['billing_yalidine_commune'] ?? '' );
        if ( $wilaya_name ) $order->set_billing_state( $wilaya_name );
        if ( $commune ) { $order->set_billing_city($commune); $order->set_billing_address_1($commune); }
        $order->set_billing_country('DZ');
        $full = $data['billing_full_name'] ?? ($data['billing_first_name'] ?? '');
        if ( $full ) {
            $parts = preg_split('/\s+/',$full,2);
            $order->set_billing_first_name($parts[0]);
            $order->set_billing_last_name( isset($parts[1]) && $parts[1]!=='' ? $parts[1] : '.' );
        }
    }

    public function save_order_meta( $order_id ) {
        $order = wc_get_order($order_id);
        if ( ! $order ) return;
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $wilaya = isset($_POST['billing_wilaya']) ? sanitize_text_field( wp_unslash($_POST['billing_wilaya']) ) : '';
        $wilaya_id = isset($_POST['cca_wilaya_id']) ? absint($_POST['cca_wilaya_id']) : ( is_numeric($wilaya) ? absint($wilaya) : 0 );
        $wilaya_name = isset($_POST['cca_wilaya_name']) ? sanitize_text_field( wp_unslash($_POST['cca_wilaya_name']) ) : '';
        if ( ! $wilaya_name ) $wilaya_name = $wilaya_id ? $this->get_wilaya_name($wilaya_id) : $wilaya;
        if ( ! $wilaya_name ) $wilaya_name = $wilaya;
        $commune = isset($_POST['billing_commune']) ? sanitize_text_field( wp_unslash($_POST['billing_commune']) ) : '';
        $commune_id = isset($_POST['cca_commune_id']) ? absint($_POST['cca_commune_id']) : 0;
        $commune_name = $commune;
        $delivery = isset($_POST['billing_delivery_type']) ? sanitize_text_field( wp_unslash($_POST['billing_delivery_type']) ) : 'home';
        $delivery_norm = ( $delivery === 'desk' || $delivery === 'stopdesk' ) ? 'stopdesk' : 'home';
        $stopdesk_id = isset($_POST['cca_stopdesk_id']) ? absint($_POST['cca_stopdesk_id']) : 0;
        $stopdesk_name = isset($_POST['cca_stopdesk_name']) ? sanitize_text_field( wp_unslash($_POST['cca_stopdesk_name']) ) : '';
        $full = isset($_POST['billing_full_name']) ? sanitize_text_field( wp_unslash($_POST['billing_full_name']) ) : '';
        // phpcs:enable
        $map = array(
            '_cca_wilaya_id'     => $wilaya_id,
            '_cca_wilaya_name'   => $wilaya_name,
            '_cca_commune_id'    => $commune_id,
            '_cca_commune_name'  => $commune_name,
            '_cca_delivery_type' => $delivery_norm,
            '_cca_stopdesk_id'   => $stopdesk_id,
            '_cca_stopdesk_name' => $stopdesk_name,
            '_cca_fee'           => WC()->session ? (float) WC()->session->get('cca_fee') : 0,
            // legacy keys for backward compat with old custom-checkout
            '_billing_wilaya'    => $wilaya_name,
            '_billing_commune'   => $commune_name,
            '_billing_delivery_type'=> $delivery_norm === 'stopdesk' ? 'Stop desk' : 'Livraison à domicile',
            '_billing_full_name' => $full,
            '_billing_wilaya_id' => $wilaya_id,
            '_billing_commune_id'=> $commune_id,
        );
        foreach($map as $k=>$v) $order->update_meta_data($k,$v);
        // also legacy post_meta for original display
        $order->save();
        // also update_post_meta for old code paths
        if ( $full ) update_post_meta($order_id,'_billing_first_name',$full);
        update_post_meta($order_id,'_billing_address_1', $wilaya_name . ' - ' . $commune_name );
    }

    public function display_admin( $order ) {
        $wilaya = $order->get_meta('_cca_wilaya_name') ?: $order->get_meta('_billing_wilaya');
        $commune= $order->get_meta('_cca_commune_name') ?: $order->get_meta('_billing_commune');
        $type   = $order->get_meta('_cca_delivery_type') ?: $order->get_meta('_billing_delivery_type');
        $stop   = $order->get_meta('_cca_stopdesk_name');
        $fee    = $order->get_meta('_cca_fee');
        if ( ! $wilaya && ! $commune ) return;
        echo '<div class="cca-meta" style="margin-top:10px;padding:8px;background:#f9f9f9;border:1px solid #eee;">';
        echo '<h4>Custom Checkout – Yalidine</h4>';
        echo '<p><strong>Wilaya:</strong> '.esc_html($wilaya).'</p>';
        echo '<p><strong>Commune:</strong> '.esc_html($commune).'</p>';
        $label = ($type==='stopdesk' || $type==='Stop desk') ? 'Stop desk' : 'Domicile';
        echo '<p><strong>Mode:</strong> '.esc_html($label).'</p>';
        if($stop) echo '<p><strong>Stop desk:</strong> '.esc_html($stop).'</p>';
        if($fee) echo '<p><strong>Frais:</strong> '.wc_price($fee).'</p>';
        $track = $order->get_meta('_cca_tracking');
        if($track) echo '<p><strong>Tracking:</strong> <code>'.esc_html($track).'</code></p>';
        echo '</div>';
    }

    public function store_session_from_post( $posted_data ) {
        parse_str($posted_data, $data);
        $wilaya_id = isset($data['cca_wilaya_id']) ? absint($data['cca_wilaya_id']) : ( isset($data['billing_wilaya']) && is_numeric($data['billing_wilaya']) ? absint($data['billing_wilaya']) : 0 );
        $commune_id= isset($data['cca_commune_id']) ? absint($data['cca_commune_id']) : 0;
        $commune_name = isset($data['billing_commune']) ? sanitize_text_field($data['billing_commune']) : '';
        $delivery = isset($data['billing_delivery_type']) ? sanitize_text_field($data['billing_delivery_type']) : 'home';
        $delivery = ($delivery==='desk'||$delivery==='stopdesk')?'stopdesk':'home';
        if ( WC()->session ) {
            WC()->session->set('cca_wilaya_id',$wilaya_id);
            WC()->session->set('cca_wilaya_name',$this->get_wilaya_name($wilaya_id));
            WC()->session->set('cca_commune_id',$commune_id);
            WC()->session->set('cca_commune_name',$commune_name);
            WC()->session->set('cca_delivery_type',$delivery);
            if(isset($data['cca_stopdesk_id'])) WC()->session->set('cca_stopdesk_id', absint($data['cca_stopdesk_id']));
            if(isset($data['cca_fee']) && ''!==$data['cca_fee']) WC()->session->set('cca_fee',(float)$data['cca_fee']);
        }
        if ( $wilaya_id && $commune_id ) {
            $billable = CCA_API::resolve_billable_for_fee();
            $fee = CCA_API::get_price_for_commune($wilaya_id,$commune_id,$delivery,$billable);
            if ( null !== $fee && WC()->session ) WC()->session->set('cca_fee',$fee);
        } elseif ( WC()->session ) WC()->session->set('cca_fee',0);
        if ( WC()->customer ) {
            WC()->customer->set_billing_country('DZ');
            WC()->customer->set_shipping_country('DZ');
            WC()->customer->set_calculated_shipping(true);
        }
    }

    /**
     * Snapshot of the delivery selection stored in the WC session.
     * Handed to the frontend so a page refresh restores what was chosen.
     */
    public static function session_state() {
        $s = WC()->session;
        if ( ! $s ) {
            return array(
                'wilaya_id'    => 0,
                'wilaya_name'  => '',
                'commune_id'   => 0,
                'commune_name' => '',
                'delivery'     => '',
                'stopdesk_id'  => 0,
                'stopdesk_name'=> '',
                'fee'          => 0,
            );
        }
        return array(
            'wilaya_id'    => (int) $s->get('cca_wilaya_id'),
            'wilaya_name'  => (string) $s->get('cca_wilaya_name'),
            'commune_id'   => (int) $s->get('cca_commune_id'),
            'commune_name' => (string) $s->get('cca_commune_name'),
            'delivery'     => (string) $s->get('cca_delivery_type'),
            'stopdesk_id'  => (int) $s->get('cca_stopdesk_id'),
            'stopdesk_name'=> (string) $s->get('cca_stopdesk_name'),
            'fee'          => (float) $s->get('cca_fee'),
        );
    }

    public function ajax_get_communes() {
        check_ajax_referer('cca_checkout','nonce');
        $wilaya_id = isset($_POST['wilaya_id']) ? absint($_POST['wilaya_id']) : 0;
        if ( ! $wilaya_id ) wp_send_json_error( array('message'=> __( 'Wilaya invalide.', 'custom-checkout-algeria' )));
        $result = CCA_API::get_communes(array('wilaya_id'=>$wilaya_id,'is_deliverable'=>1,'page_size'=>1000));
        if ( empty($result['success']) ) {
            // Fallback to static? still error but allow empty to show static?
            wp_send_json_error( array('message'=> $result['error'] ?? __( 'Erreur API.', 'custom-checkout-algeria' )));
        }
        $items=array();
        $data = $result['data'];
        $list = $data['data'] ?? $data;
        if ( is_array($list) ) foreach($list as $c){ $items[] = array('id'=>(int)($c['id']??0),'name'=>$c['name']??'','has_stop_desk'=>!empty($c['has_stop_desk'])); }
        wp_send_json_success( array('communes'=>$items));
    }

    public function ajax_get_centers() {
        check_ajax_referer('cca_checkout','nonce');
        $wilaya_id = isset($_POST['wilaya_id']) ? absint($_POST['wilaya_id']) : 0;
        $commune_id= isset($_POST['commune_id']) ? absint($_POST['commune_id']) : 0;
        if ( ! $wilaya_id ) wp_send_json_error( array('message'=> __( 'Wilaya invalide.', 'custom-checkout-algeria' )));
        // Fetch the whole wilaya once (one API call per wilaya per hour thanks to the
        // transient) and let the browser filter per commune. Browsing 10 communes in a
        // wilaya used to cost 10 Yalidine calls.
        $result = CCA_API::get_centers( array('wilaya_id'=>$wilaya_id,'page_size'=>1000) );
        if ( empty($result['success']) ) wp_send_json_error( array('message'=> $result['error'] ?? __( 'Erreur API.', 'custom-checkout-algeria' ) ));

        $items = self::map_centers( $result );

        // Only filterable client-side when every centre carries its commune.
        $by_commune = ! empty( $items );
        foreach ( $items as $c ) {
            if ( empty( $c['commune_id'] ) ) { $by_commune = false; break; }
        }

        // Defensive fallback: no commune info in the payload, so ask the API directly.
        if ( ! $by_commune && $commune_id ) {
            $result = CCA_API::get_centers( array('wilaya_id'=>$wilaya_id,'page_size'=>1000,'commune_id'=>$commune_id) );
            if ( empty($result['success']) ) wp_send_json_error( array('message'=> $result['error'] ?? __( 'Erreur API.', 'custom-checkout-algeria' ) ));
            $items = self::map_centers( $result );
        }

        wp_send_json_success( array('centers'=>$items,'by_commune'=>$by_commune) );
    }

    /**
     * Normalise a Yalidine `centers` payload, keeping address + commune so two
     * desks in the same wilaya stay distinguishable in the list.
     */
    private static function map_centers( $result ) {
        $items = array();
        $data  = $result['data'] ?? array();
        $list  = $data['data'] ?? $data;
        if ( ! is_array($list) ) return $items;
        foreach ( $list as $c ) {
            if ( ! is_array($c) ) continue;
            $id = (int) ( $c['center_id'] ?? $c['id'] ?? 0 );
            if ( ! $id ) continue;
            $items[] = array(
                'id'           => $id,
                'name'         => (string) ( $c['name'] ?? '' ),
                'address'      => (string) ( $c['address'] ?? '' ),
                'commune_id'   => (int) ( $c['commune_id'] ?? 0 ),
                'commune_name' => (string) ( $c['commune_name'] ?? $c['commune'] ?? '' ),
            );
        }
        return $items;
    }

    public function ajax_get_fee() {
        check_ajax_referer('cca_checkout','nonce');
        $to = isset($_POST['wilaya_id']) ? absint($_POST['wilaya_id']) : 0;
        $commune_id = isset($_POST['commune_id']) ? absint($_POST['commune_id']) : 0;
        $delivery = isset($_POST['delivery_type']) ? sanitize_text_field( wp_unslash($_POST['delivery_type']) ) : 'home';
        $delivery = ($delivery==='desk'||$delivery==='stopdesk')?'stopdesk':'home';
        $billable = null;
        if ( isset($_POST['billable_weight']) && '' !== $_POST['billable_weight'] ) $billable = (float) $_POST['billable_weight'];
        elseif ( isset($_POST['product_weight']) ) {
            $pw = (float)$_POST['product_weight']; $pl=(float)($_POST['product_length']??0); $pwi=(float)($_POST['product_width']??0); $ph=(float)($_POST['product_height']??0); $qty=max(1,(int)($_POST['product_quantity']??1));
            $billable = CCA_API::compute_billable_weight(array(array('weight'=>$pw,'length'=>$pl,'width'=>$pwi,'height'=>$ph,'quantity'=>$qty)));
        } else {
            $billable = CCA_API::resolve_billable_for_fee();
        }
        $fee = CCA_API::get_price_for_commune($to,$commune_id,$delivery,$billable);
        if ( null === $fee ) {
            if ( WC()->session ) WC()->session->set('cca_fee',0);
            wp_send_json_error( array('message'=> __( 'Aucun tarif pour cette commune.', 'custom-checkout-algeria' )));
        }
        if ( WC()->session ) { WC()->session->set('cca_fee',$fee); WC()->session->set('cca_wilaya_id',$to); WC()->session->set('cca_commune_id',$commune_id); WC()->session->set('cca_delivery_type',$delivery); }
        wp_send_json_success( array('fee'=>$fee,'fee_formatted'=> wc_price($fee)));
    }

    public function add_fee( $cart ) {
        if ( is_admin() && ! defined('DOING_AJAX') ) return;
        if ( ! $cart instanceof WC_Cart ) return;

        // Only add the delivery fee on checkout. The WC session persists across
        // the site, so leaving checkout without clearing it would leak the fee
        // to the sidebar cart and other pages.
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            return;
        }

        $fee = 0.0;
        if ( WC()->session ) {
            $sf = WC()->session->get('cca_fee');
            if ( null !== $sf && '' !== $sf ) {
                $fee = (float) $sf;
            }
        }
        if ( $fee <= 0 ) return;
        // Avoid double count if shipping already covers
        $ship = (float) $cart->get_shipping_total();
        if ( $ship >= $fee - 0.01 && $ship > 0 ) return;
        $delivery = WC()->session ? WC()->session->get('cca_delivery_type') : 'home';
        $label = ($delivery === 'stopdesk') ? __( 'Livraison (Stop desk)', 'custom-checkout-algeria' ) : __( 'Livraison à domicile', 'custom-checkout-algeria' );
        $cart->add_fee( $label, $fee, false );
    }

    public function bust_shipping_cache( $packages ) {
        if ( ! is_array( $packages ) ) return $packages;
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return $packages;
        }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            return $packages;
        }
        $fee = WC()->session ? WC()->session->get('cca_fee') : 0;
        $delivery = WC()->session ? WC()->session->get('cca_delivery_type') : '';
        foreach($packages as $i=>$p){ $packages[$i]['cca_fee']=$fee; $packages[$i]['cca_delivery']=$delivery; }
        return $packages;
    }

    private function get_wilaya_name( $id ) {
        $id = absint($id);
        if ( ! $id ) return '';
        // Try cached transient first to avoid live API on order save
        $cached = get_transient('cca_wilayas');
        if ( false !== $cached && ! empty($cached['success']) ) {
            $data = $cached['data'];
            $list = $data['data'] ?? $data;
            if ( is_array($list) ) foreach($list as $w){ if((int)($w['id']??0)===$id) return $w['name'] ?? ''; }
        }
        $res = CCA_API::get_wilayas();
        if ( empty($res['success']) ) return '';
        $data = $res['data'];
        $list = $data['data'] ?? $data;
        if ( ! is_array($list) ) return '';
        foreach($list as $w){ if((int)($w['id']??0)===$id) return $w['name'] ?? ''; }
        return '';
    }
}
new CCA_Checkout();
