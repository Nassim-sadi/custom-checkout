<?php
defined( 'ABSPATH' ) || exit;

class CCA_Orders {
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ), 20 );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column' ), 20, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ), 20 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos' ), 20, 2 );
        add_action( 'wp_ajax_cca_send_parcel', array( $this, 'ajax_send' ) );
    }
    public function add_box() {
        $screens = array('shop_order');
        if ( class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $screens[] = wc_get_page_screen_id('shop-order');
        }
        foreach( array_unique($screens) as $screen ){
            add_meta_box( 'cca-yalidine-parcel', __( 'Yalidine – Colis', 'custom-checkout-algeria' ), array($this,'render_box'), $screen, 'side', 'high' );
        }
    }
    public function render_box( $post_or_order ) {
        $order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
        if ( ! $order ) return;
        $this->render_actions($order,false);
    }
    public function add_column($cols){
        $new=array(); foreach($cols as $k=>$l){ $new[$k]=$l; if('order_status'===$k) $new['cca_yalidine']=__( 'Yalidine', 'custom-checkout-algeria' ); }
        if(!isset($new['cca_yalidine'])) $new['cca_yalidine']=__('Yalidine','custom-checkout-algeria');
        return $new;
    }
    public function render_column($col,$post_id){ if('cca_yalidine'!==$col) return; $o=wc_get_order($post_id); if($o) $this->render_actions($o,true); }
    public function render_hpos($col,$order){ if('cca_yalidine'!==$col) return; if($order instanceof WC_Order) $this->render_actions($order,true); }

    private function render_actions( $order, $compact=false ){
        $tracking = $order->get_meta('_cca_tracking');
        $label    = $order->get_meta('_cca_label');
        $has_dest = (bool) $order->get_meta('_cca_wilaya_id');
        echo '<div class="cca-order-actions" data-order-id="'.esc_attr($order->get_id()).'">';
        if($tracking){
            echo '<p><strong>Tracking:</strong> <code>'.esc_html($tracking).'</code></p>';
            if($label) echo '<p><a class="button button-small" href="'.esc_url($label).'" target="_blank" rel="noopener">Télécharger bordereau</a></p>';
        } elseif($has_dest && CCA_Settings::is_configured()){
            echo '<button type="button" class="button button-primary cca-send-parcel">'.esc_html( $compact ? __('Envoyer','custom-checkout-algeria') : __('Envoyer à Yalidine','custom-checkout-algeria')).'</button>';
            echo '<span class="cca-spinner spinner" style="float:none;margin:0 0 0 6px;"></span>';
            echo '<p class="cca-msg" style="display:none;"></p>';
        } else {
            echo '<span class="cca-muted">—</span>';
        }
        // Debug info for stopdesk fix
        $type=$order->get_meta('_cca_delivery_type'); $sd=$order->get_meta('_cca_stopdesk_name');
        if($type==='stopdesk' && $sd) echo '<p style="font-size:11px;color:#666;">Stop desk: '.esc_html($sd).' (ID '.esc_html($order->get_meta('_cca_stopdesk_id')).')</p>';
        echo '</div>';
    }

    public function ajax_send(){
        check_ajax_referer('cca_admin','nonce');
        if(!current_user_can('manage_woocommerce')) wp_send_json_error(array('message'=>__('Permission refusée.','custom-checkout-algeria')),403);
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);
        if(!$order) wp_send_json_error(array('message'=>__('Commande introuvable.','custom-checkout-algeria')));
        if($order->get_meta('_cca_tracking')) wp_send_json_error(array('message'=>__('Colis déjà créé.','custom-checkout-algeria')));
        $payload = $this->build_payload($order);
        if(is_wp_error($payload)) wp_send_json_error(array('message'=>$payload->get_error_message()));
        $res = CCA_API::create_parcels(array($payload));
        if(empty($res['success'])) wp_send_json_error(array('message'=> $res['error'] ?? __('Échec création colis.','custom-checkout-algeria')));
        $data = $res['data'];
        $order_key = (string)$payload['order_id'];
        $entry = null;
        if(is_array($data) && isset($data[$order_key])) $entry=$data[$order_key];
        elseif(is_array($data)) { $first=reset($data); if(is_array($first)) $entry=$first; }
        if(!$entry || empty($entry['success'])) wp_send_json_error(array('message'=> $entry['message'] ?? __('Échec création colis.','custom-checkout-algeria')));
        $tracking = sanitize_text_field($entry['tracking'] ?? '');
        $label = esc_url_raw($entry['label'] ?? '');
        $order->update_meta_data('_cca_tracking',$tracking);
        $order->update_meta_data('_cca_label',$label);
        if(isset($entry['import_id'])) $order->update_meta_data('_cca_import_id', absint($entry['import_id']));
        $order->update_meta_data('_cca_status','Créé');
        $order->add_order_note(sprintf(__('Colis Yalidine créé. Tracking: %s','custom-checkout-algeria'),$tracking));
        $order->save();
        CCA_API::log("Parcel created for order #$order_id tracking $tracking");
        wp_send_json_success(array('message'=>__('Colis envoyé.','custom-checkout-algeria'),'tracking'=>$tracking,'label'=>$label));
    }

    private function build_payload( $order ){
        $wilaya_id = absint($order->get_meta('_cca_wilaya_id'));
        $wilaya_name = $order->get_meta('_cca_wilaya_name');
        $commune_id = absint($order->get_meta('_cca_commune_id'));
        $commune_name= $order->get_meta('_cca_commune_name');
        $delivery = $order->get_meta('_cca_delivery_type');
        $stopdesk_id = absint($order->get_meta('_cca_stopdesk_id'));
        $stopdesk_name= $order->get_meta('_cca_stopdesk_name');
        if(!$wilaya_name || !$commune_name) return new WP_Error('cca_missing', __('Wilaya/commune manquantes.','custom-checkout-algeria'));
        $from_name = CCA_Settings::get('from_wilaya_name');
        if(!$from_name) return new WP_Error('cca_from', __('Wilaya d\'expédition non configurée.','custom-checkout-algeria'));
        $is_stopdesk = ($delivery==='stopdesk');

        // FIX: For stopdesk, resolve centre and use centre's commune name (not user's commune) – fixes mismatch bug from createk.
        $final_commune = $commune_name;
        $final_stopdesk_id = $stopdesk_id;
        if($is_stopdesk){
            if(!$stopdesk_id) return new WP_Error('cca_stopdesk', __('Stop desk manquant.','custom-checkout-algeria'));
            $centres_res = CCA_API::get_centers(array('wilaya_id'=>$wilaya_id,'page_size'=>1000));
            $list = array();
            if(!empty($centres_res['success'])){ $d=$centres_res['data']; $list=$d['data']??$d; if(!is_array($list)) $list=array(); }
            $found=null;
            foreach($list as $c){ $cid=(int)($c['center_id']??$c['id']??0); if($cid===$stopdesk_id){ $found=$c; break; } }
            if(!$found) return new WP_Error('cca_centre', sprintf(__('Centre %d introuvable pour wilaya %d.','custom-checkout-algeria'),$stopdesk_id,$wilaya_id));
            if(!empty($found['commune_name'])) $final_commune = $found['commune_name'];
            elseif(!empty($found['commune'])) $final_commune = $found['commune'];
            $final_stopdesk_id = (int)($found['center_id']??$found['id']);
        }

        $firstname = $order->get_billing_first_name() ?: $order->get_shipping_first_name();
        $lastname  = $order->get_billing_last_name() ?: $order->get_shipping_last_name();
        // Fallback to full name split if billing first/last are dot
        if ( ($lastname==='.' || empty($lastname)) && $order->get_meta('_cca_wilaya_name') ) {
            $full = $order->get_meta('_billing_full_name');
            if($full){ $parts=preg_split('/\s+/',$full,2); $firstname=$parts[0]; $lastname=isset($parts[1])? $parts[1]:'.'; }
        }
        $phone = $order->get_billing_phone();
        $phone = preg_replace('/\s+/','',$phone);
        if($phone && '0'!==substr($phone,0,1) && preg_match('/^213/',$phone)) $phone='0'.substr($phone,3);
        $address = $order->get_billing_address_1();
        if($order->get_billing_address_2()) $address .= ' '.$order->get_billing_address_2();
        if(empty($address)) $address = $final_commune;

        $products=array();
        foreach($order->get_items() as $item){ $products[]=$item->get_name().' x'.$item->get_quantity(); }
        $product_list=implode(', ',$products);
        if(strlen($product_list)>255) $product_list=substr($product_list,0,252).'...';
        if(empty($product_list)) $product_list='Commande #'.$order->get_id();

        // Price = total - shipping_fee (if fee stored as fee, deduct)
        $ship = (float)$order->get_shipping_total() + (float)$order->get_shipping_tax();
        if($ship<=0){ foreach($order->get_fees() as $fee){ $n=strtolower($fee->get_name()); if(false!==strpos($n,'livraison')||false!==strpos($n,'yalidine')||false!==strpos($n,'cca')) $ship+= (float)$fee->get_total() + (float)$fee->get_total_tax(); } }
        if($ship<=0) $ship=(float)$order->get_meta('_cca_fee');
        $price = (int) round( (float)$order->get_total() - $ship );
        if($price<0) $price=0; if($price>150000) $price=150000;

        $s = CCA_Settings::all();
        $payload = array(
            'order_id'         => (string)$order->get_id(),
            'from_wilaya_name' => $from_name,
            'firstname'        => $firstname ?: 'Client',
            'familyname'       => $lastname ?: '.',
            'contact_phone'    => $phone,
            'address'          => $address ?: $final_commune,
            'to_commune_name'  => $final_commune,
            'to_wilaya_name'   => $wilaya_name,
            'product_list'     => $product_list,
            'price'            => $price,
            'do_insurance'     => ($s['do_insurance']==='1'),
            'declared_value'   => $price,
            'length'           => (int)($s['default_length']?:10),
            'width'            => (int)($s['default_width']?:10),
            'height'           => (int)($s['default_height']?:10),
            'weight'           => (int) max(1, round((float)($s['default_weight']?:1))),
            'freeshipping'     => ($s['freeshipping']==='1'),
            'is_stopdesk'      => (bool)$is_stopdesk,
            'has_exchange'     => false,
        );
        if($is_stopdesk) $payload['stopdesk_id'] = $final_stopdesk_id;
        return $payload;
    }
}
new CCA_Orders();
