<?php
defined( 'ABSPATH' ) || exit;

/**
 * Yalidine API client for Custom Checkout Algeria.
 * Single option `cca_settings` (array) holds api_id/token/from_wilaya.
 * Caching: wilayas/communes/centres HOUR, route (fees+oversize) 60min.
 */
class CCA_API {

    /**
     * @return array{api_id:string,api_token:string}
     */
    public static function get_credentials( $api_id = null, $api_token = null ) {
        $s = get_option( 'cca_settings', array() );
        return array(
            'api_id'    => $api_id ? $api_id : ( $s['api_id'] ?? '' ),
            'api_token' => $api_token ? $api_token : ( $s['api_token'] ?? '' ),
        );
    }

    public static function test_credentials( $api_id, $api_token ) {
        return self::request( 'wilayas/', 'GET', array(), $api_id, $api_token );
    }

    public static function get_wilayas( $args = array() ) {
        $s = get_option( 'cca_settings', array() );
        // If credentials not set, still use generic cache but return empty to force fallback.
        $creds = self::get_credentials();
        if ( empty( $creds['api_id'] ) || empty( $creds['api_token'] ) ) {
            // Try static fallback indirectly – caller will handle.
            $cached = get_transient( 'cca_wilayas' );
            if ( false !== $cached ) return $cached;
            return array( 'success' => false, 'error' => 'Missing credentials' );
        }
        $cache_key = 'cca_wilayas_' . md5( wp_json_encode( $args ) );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) return $cached;
        $result = self::request( 'wilayas/?' . http_build_query( wp_parse_args( $args, array( 'page_size' => 1000 ) ) ) );
        if ( ! empty( $result['success'] ) ) {
            set_transient( $cache_key, $result, HOUR_IN_SECONDS );
            set_transient( 'cca_wilayas', $result, HOUR_IN_SECONDS );
        }
        return $result;
    }

    public static function get_communes( $args = array() ) {
        $cache_key = 'cca_communes_' . md5( wp_json_encode( $args ) );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) return $cached;
        $result = self::request( 'communes/?' . http_build_query( wp_parse_args( $args, array( 'page_size' => 1000 ) ) ) );
        if ( ! empty( $result['success'] ) ) {
            set_transient( $cache_key, $result, HOUR_IN_SECONDS );
        }
        return $result;
    }

    public static function get_centers( $args = array() ) {
        $cache_key = 'cca_centers_' . md5( wp_json_encode( $args ) );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) return $cached;
        $result = self::request( 'centers/?' . http_build_query( wp_parse_args( $args, array( 'page_size' => 1000 ) ) ) );
        if ( ! empty( $result['success'] ) ) {
            set_transient( $cache_key, $result, HOUR_IN_SECONDS );
        }
        return $result;
    }

    /**
     * Route fees + oversize, cached 60min (not WEEK – tariffs change).
     * Returns raw request result array (success+data).
     */
    public static function get_fees( $from_wilaya_id, $to_wilaya_id ) {
        $from = absint( $from_wilaya_id );
        $to   = absint( $to_wilaya_id );
        $cache_key = "cca_yali_route_{$from}_{$to}";
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) return $cached;
        $result = self::request( "fees/?from_wilaya_id={$from}&to_wilaya_id={$to}" );
        if ( ! empty( $result['success'] ) ) {
            set_transient( $cache_key, $result, 60 * MINUTE_IN_SECONDS );
        }
        return $result;
    }

    public static function create_parcels( $parcels ) {
        return self::request( 'parcels/', 'POST', $parcels );
    }

    /**
     * Compute billable weight max(actual, volumetric) where volumetric = L*W*H*0.0002 per qty.
     * $items: [ ['weight'=>kg, 'length'=>cm, 'width'=>cm, 'height'=>cm, 'quantity'=>int], ...]
     */
    public static function compute_billable_weight( $items ) {
        $actual_w = 0;
        $volumetric_w = 0;
        foreach ( $items as $item ) {
            $qty = max( 1, (int) ( $item['quantity'] ?? 1 ) );
            $aw  = (float) ( $item['weight'] ?? 0 ) * $qty;
            $actual_w += $aw;
            $l = (float) ( $item['length'] ?? 0 );
            $w = (float) ( $item['width'] ?? 0 );
            $h = (float) ( $item['height'] ?? 0 );
            $vw = ( $l * $w * $h * 0.0002 ) * $qty;
            $volumetric_w += $vw;
        }
        return max( $actual_w, $volumetric_w );
    }

    /**
     * Resolve billable from cart or single product dims (with defaults).
     * @return float
     */
    public static function resolve_billable_for_fee() {
        $s = get_option( 'cca_settings', array() );
        $def_w = isset( $s['default_weight'] ) ? (float) $s['default_weight'] : 1;
        $def_l = isset( $s['default_length'] ) ? (float) $s['default_length'] : 10;
        $def_wi= isset( $s['default_width'] )  ? (float) $s['default_width']  : 10;
        $def_h = isset( $s['default_height'] ) ? (float) $s['default_height'] : 10;

        if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
            $items = array();
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $p = $cart_item['data'];
                if ( ! $p ) continue;
                $qty = (int) $cart_item['quantity'];
                $w = (float) $p->get_weight(); if ( $w <= 0 ) $w = $def_w;
                $l = (float) $p->get_length(); if ( $l <= 0 ) $l = $def_l;
                $wi= (float) $p->get_width();  if ( $wi<= 0 ) $wi= $def_wi;
                $h = (float) $p->get_height(); if ( $h <= 0 ) $h = $def_h;
                $items[] = array( 'weight'=>$w, 'length'=>$l, 'width'=>$wi, 'height'=>$h, 'quantity'=>$qty );
            }
            if ( ! empty( $items ) ) return self::compute_billable_weight( $items );
        }
        // Single product fallback – use defaults as single item qty1
        return self::compute_billable_weight( array( array( 'weight'=>$def_w, 'length'=>$def_l, 'width'=>$def_wi, 'height'=>$def_h, 'quantity'=>1 ) ) );
    }

    /**
     * Get price for commune with overweight.
     * Always express (no economic).
     * @return float|null null if not found
     */
    public static function get_price_for_commune( $to_wilaya_id, $commune_id, $delivery = 'home', $billable = null ) {
        $s = get_option( 'cca_settings', array() );
        $from = isset( $s['from_wilaya_id'] ) ? absint( $s['from_wilaya_id'] ) : 0;
        if ( ! $from || ! $to_wilaya_id || ! $commune_id ) return null;
        if ( null === $billable ) $billable = self::resolve_billable_for_fee();
        $result = self::get_fees( $from, absint( $to_wilaya_id ) );
        if ( empty( $result['success'] ) ) return null;
        $data = $result['data'] ?? array();
        // data shape may be {data: {per_commune,...}} or {per_commune,...}
        $per_commune = $data['per_commune'] ?? ($data['data']['per_commune'] ?? null);
        $oversize = isset( $data['oversize_fee'] ) ? (float) $data['oversize_fee'] : ( isset( $data['data']['oversize_fee'] ) ? (float) $data['data']['oversize_fee'] : 0 );
        if ( null === $per_commune ) return null;
        $cid_str = (string) absint( $commune_id );
        $commune_fees = $per_commune[ $cid_str ] ?? ($per_commune[ absint( $commune_id ) ] ?? null);
        if ( null === $commune_fees ) return null;
        $key = ( 'stopdesk' === $delivery || 'desk' === $delivery ) ? 'express_desk' : 'express_home';
        $base = isset( $commune_fees[ $key ] ) ? (float) $commune_fees[ $key ] : 0;
        if ( $base <= 0 ) return null;
        $over = 0;
        if ( $billable > 5 && $oversize > 0 ) $over = ( $billable - 5 ) * $oversize;
        return $base + $over;
    }

    public static function clear_cache() {
        global $wpdb;
        // Delete via SQL (many md5-suffixed keys) AND drop the matching object-cache
        // entries. Without wp_cache_delete the values stay readable for the rest of
        // the request, and with a persistent cache (Redis/Memcached) the raw SQL
        // would not clear them at all.
        $prefixes = array( 'cca_wilayas', 'cca_communes', 'cca_centers', 'cca_yali_route' );
        foreach ( $prefixes as $prefix ) {
            foreach ( array( '_transient_' . $prefix, '_transient_timeout_' . $prefix ) as $like ) {
                $pattern = $wpdb->esc_like( $like ) . '%';
                $names   = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
                if ( $names ) {
                    foreach ( $names as $name ) {
                        wp_cache_delete( $name, 'options' );
                    }
                    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
                }
                wp_cache_delete( $like, 'options' );
            }
        }
        // Handles persistent object caches, where the rows above are not authoritative.
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'options' );
        }
        delete_transient( 'cca_wilayas' );
    }

    public static function log( $message, $level = 'info' ) {
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->log( $level, $message, array( 'source' => 'cca-yalidine' ) );
        }
    }

    public static function request( $endpoint, $method = 'GET', $body = array(), $api_id = null, $api_token = null ) {
        $creds = self::get_credentials( $api_id, $api_token );
        $api_id = $creds['api_id'];
        $api_token = $creds['api_token'];
        if ( empty( $api_id ) || empty( $api_token ) ) {
            return array( 'success'=>false, 'error'=> __( 'Identifiants API Yalidine manquants.', 'custom-checkout-algeria' ), 'code'=>0 );
        }
        $url  = CCA_API_BASE . ltrim( $endpoint, '/' );
        $args = array(
            'method'  => strtoupper( $method ),
            'timeout' => 30,
            'headers' => array(
                'X-API-ID'     => $api_id,
                'X-API-TOKEN'  => $api_token,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ),
        );
        if ( ! empty( $body ) && in_array( strtoupper( $method ), array( 'POST','PATCH','PUT' ), true ) ) {
            $args['body'] = wp_json_encode( $body );
        }
        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return array( 'success'=>false, 'error'=>$response->get_error_message(), 'code'=>0 );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        if ( $code === 429 ) {
            $retry = wp_remote_retrieve_header( $response, 'retry-after' );
            // Do NOT sleep – return immediately to avoid blocking checkout (JS will retry).
            return array( 'success'=>false, 'error'=> sprintf( __( 'Quota API dépassé. Réessayez dans %s seconde(s).', 'custom-checkout-algeria' ), $retry ? $retry : '60' ), 'code'=>429, 'data'=>$data );
        }
        if ( $code < 200 || $code >= 300 ) {
            $msg = __( 'Erreur API Yalidine.', 'custom-checkout-algeria' );
            if ( is_array( $data ) ) {
                if ( ! empty( $data['error'] ) ) $msg = is_array($data['error'])? wp_json_encode($data['error']): (string)$data['error'];
                elseif ( ! empty( $data['message'] ) ) $msg = (string)$data['message'];
            }
            return array( 'success'=>false, 'error'=>$msg, 'code'=>$code, 'data'=>$data );
        }
        return array( 'success'=>true, 'data'=>$data, 'code'=>$code );
    }
}
