<?php
/**
 * Plugin Name: Custom Checkout Algeria
 * Description: Simplified WooCommerce checkout with Wilaya/Commune selection and Algerian phone validation.
 * Version: 1.0
 * Author: Nassim Studio
 * Author URI: https://nassimstudio.com
 * Text Domain: custom-checkout-algeria
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Checkout_Algeria {

    public function __construct() {
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Modify checkout fields
        add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ) );

        // Validate checkout fields
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_custom_fields' ) );

        // Save custom fields to order
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_fields' ) );

        // Display custom fields in admin
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_custom_fields_admin' ), 10, 1 );

        // Override success message
        add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'override_success_message' ), 20, 2 );
        
        // Hide shipping methods if not needed (optional, user said no price)
        // add_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_false' );
    }

    public function enqueue_assets() {
        if ( is_checkout() ) {
            wp_enqueue_style( 'custom-checkout-style', plugins_url( 'assets/css/checkout.css', __FILE__ ) );
            
            wp_enqueue_script( 'cities-data', plugins_url( 'assets/js/cities-data.js', __FILE__ ), array(), '1.0', true );
            wp_enqueue_script( 'custom-checkout-script', plugins_url( 'assets/js/checkout.js', __FILE__ ), array( 'jquery', 'cities-data' ), '1.0', true );
        }
    }

    public function customize_checkout_fields( $fields ) {
        // Remove unneeded billing fields
        unset( $fields['billing']['billing_company'] );
        unset( $fields['billing']['billing_address_1'] );
        unset( $fields['billing']['billing_address_2'] );
        unset( $fields['billing']['billing_city'] );
        unset( $fields['billing']['billing_postcode'] );
        unset( $fields['billing']['billing_country'] );
        unset( $fields['billing']['billing_state'] );
        unset( $fields['billing']['billing_first_name'] );
        unset( $fields['billing']['billing_last_name'] );
        unset( $fields['billing']['billing_email'] );

        // Add Full Name
        $fields['billing']['billing_full_name'] = array(
            'label'       => 'Nom Complet',
            'placeholder' => 'Votre nom et prénom',
            'required'    => true,
            'class'       => array( 'form-row-wide' ),
            'clear'       => true,
            'priority'    => 10,
        );

        // Keep Phone but move it up
        $fields['billing']['billing_phone']['priority'] = 20;
        $fields['billing']['billing_phone']['class'] = array( 'form-row-wide' );
        $fields['billing']['billing_phone']['label'] = 'Numéro de téléphone';
        $fields['billing']['billing_phone']['placeholder'] = '0XXXXXXXXX';

        // Add Wilaya
        $fields['billing']['billing_wilaya'] = array(
            'type'        => 'select',
            'label'       => 'Wilaya',
            'required'    => true,
            'class'       => array( 'form-row-first' ),
            'options'     => array( '' => 'Sélectionnez votre wilaya' ),
            'priority'    => 30,
        );

        // Add Commune
        $fields['billing']['billing_commune'] = array(
            'type'        => 'select',
            'label'       => 'Commune',
            'required'    => true,
            'class'       => array( 'form-row-last' ),
            'options'     => array( '' => 'Sélectionnez votre commune' ),
            'priority'    => 40,
        );

        // Delivery Type
        $fields['billing']['billing_delivery_type'] = array(
            'type'        => 'radio',
            'label'       => 'Type de livraison',
            'required'    => true,
            'class'       => array( 'form-row-wide', 'custom-radio-group' ),
            'options'     => array(
                'home' => 'Livraison à domicile',
                'desk' => 'Stop desk (Récupérer au bureau)'
            ),
            'default'     => 'home',
            'priority'    => 50,
        );

        return $fields;
    }

    public function validate_custom_fields() {
        if ( isset( $_POST['billing_phone'] ) && ! empty( $_POST['billing_phone'] ) ) {
            if ( ! preg_match( '/^(05|06|07)[0-9]{8}$/', $_POST['billing_phone'] ) ) {
                wc_add_notice( 'Le numéro de téléphone doit être un numéro algérien valide (ex: 05XXXXXXXX, 06XXXXXXXX, 07XXXXXXXX).', 'error' );
            }
        }
    }

    public function save_custom_fields( $order_id ) {
        if ( ! empty( $_POST['billing_full_name'] ) ) {
            update_post_meta( $order_id, '_billing_full_name', sanitize_text_field( $_POST['billing_full_name'] ) );
            // Also update the standard first name for compatibility
            update_post_meta( $order_id, '_billing_first_name', sanitize_text_field( $_POST['billing_full_name'] ) );
        }
        if ( ! empty( $_POST['billing_wilaya'] ) ) {
            update_post_meta( $order_id, '_billing_wilaya', sanitize_text_field( $_POST['billing_wilaya'] ) );
        }
        if ( ! empty( $_POST['billing_commune'] ) ) {
            update_post_meta( $order_id, '_billing_commune', sanitize_text_field( $_POST['billing_commune'] ) );
        }
        if ( ! empty( $_POST['billing_delivery_type'] ) ) {
            $delivery_label = ( $_POST['billing_delivery_type'] == 'home' ) ? 'Livraison à domicile' : 'Stop desk';
            update_post_meta( $order_id, '_billing_delivery_type', $delivery_label );
        }
        
        // Combine address for WooCommerce default
        $wilaya = sanitize_text_field( $_POST['billing_wilaya'] );
        $commune = sanitize_text_field( $_POST['billing_commune'] );
        update_post_meta( $order_id, '_billing_address_1', $wilaya . ' - ' . $commune );
    }

    public function display_custom_fields_admin( $order ) {
        echo '<p><strong>Wilaya:</strong> ' . get_post_meta( $order->get_id(), '_billing_wilaya', true ) . '</p>';
        echo '<p><strong>Commune:</strong> ' . get_post_meta( $order->get_id(), '_billing_commune', true ) . '</p>';
        echo '<p><strong>Type de livraison:</strong> ' . get_post_meta( $order->get_id(), '_billing_delivery_type', true ) . '</p>';
        echo '<p><strong>Nom Complet:</strong> ' . get_post_meta( $order->get_id(), '_billing_full_name', true ) . '</p>';
    }

    public function override_success_message( $text, $order ) {
        return 'Merci pour votre commande, nous allons vous contacter pour confirmer.';
    }
}

new Custom_Checkout_Algeria();
