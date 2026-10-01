<?php
/**
 * Plugin Name: Custom Checkout Algeria
 * Description: Simplified WooCommerce checkout with Wilaya/Commune selection, Yalidine live fees (express), Stop-Desk centres per commune, and setup wizard.
 * Version: 1.2.0
 * Author: Nassim Studio
 * Author URI: https://nassimstudio.com
 * Text Domain: custom-checkout-algeria
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CCA_VERSION', '1.2.0' );
define( 'CCA_FILE', __FILE__ );
define( 'CCA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CCA_URL', plugin_dir_url( __FILE__ ) );
define( 'CCA_API_BASE', 'https://api.yalidine.app/v1/' );

require_once CCA_PATH . 'includes/class-cca-api.php';
require_once CCA_PATH . 'includes/class-cca-settings.php';
require_once CCA_PATH . 'includes/class-cca-setup-wizard.php';
require_once CCA_PATH . 'includes/class-cca-checkout.php';
require_once CCA_PATH . 'includes/class-cca-orders.php';

register_activation_hook( CCA_FILE, 'cca_activate' );
function cca_activate() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( CCA_FILE ) );
        wp_die( esc_html__( 'Custom Checkout Algeria requires WooCommerce.', 'custom-checkout-algeria' ), esc_html__( 'Plugin dependency', 'custom-checkout-algeria' ), array( 'back_link' => true ) );
    }
    if ( ! get_option( 'cca_configured' ) ) {
        update_option( 'cca_show_wizard', '1' );
    }
}

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CCA_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CCA_FILE, false );
    }
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            if ( ! current_user_can( 'activate_plugins' ) ) return;
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Custom Checkout Algeria requires WooCommerce.', 'custom-checkout-algeria' ) . '</p></div>';
        } );
        return;
    }
    load_plugin_textdomain( 'custom-checkout-algeria', false, dirname( plugin_basename( CCA_FILE ) ) . '/languages' );

    // Legacy bootstrap for backward-compat with original class.
    new Custom_Checkout_Algeria();
}, 20 );

/**
 * Legacy class kept for backward compatibility – now delegates to new modular classes.
 * New logic lives in CCA_Checkout / CCA_Settings / CCA_Orders.
 */
class Custom_Checkout_Algeria {

    public function __construct() {
        // Keep thank-you override; checkout fields now handled by CCA_Checkout.
        add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'override_success_message' ), 20, 2 );
        // Back-compat: enqueue is now in CCA_Checkout but keep original hook for themes filtering.
    }

    public function override_success_message( $text, $order ) {
        return 'Merci pour votre commande, nous allons vous contacter pour confirmer.';
    }

    // Stubs kept so external code does not fatal if calling old methods.
    public function enqueue_assets() {}
    public function customize_checkout_fields( $fields ) { return $fields; }
    public function validate_custom_fields() {}
    public function save_custom_fields( $order_id ) {}
    public function display_custom_fields_admin( $order ) {}
}
