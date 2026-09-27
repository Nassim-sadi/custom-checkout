<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap cca-settings-wrap">
    <h1><?php esc_html_e( 'Custom Checkout – Yalidine', 'custom-checkout-algeria' ); ?></h1>
    <div class="notice notice-info inline" style="padding:12px 16px;margin:16px 0;">
        <p><strong>Service tiers :</strong> Ce plugin se connecte à l’API Yalidine (https://api.yalidine.app) pour localités, frais (express) et colis. Compte Yalidine + API ID/Token requis.</p>
        <p><a href="https://yalidine.app" target="_blank">Espace marchand Yalidine</a> | <a href="https://createksolution.com" target="_blank">Createk</a> | <a href="<?php echo esc_url( admin_url('admin.php?page=cca-setup') ); ?>">Relancer l’assistant</a></p>
    </div>
    <form method="post">
        <?php wp_nonce_field( 'cca_save_settings', 'cca_settings_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tr><th scope="row"><label for="cca_api_id"><?php esc_html_e( 'API ID', 'custom-checkout-algeria' ); ?></label></th>
                <td><input name="cca[api_id]" id="cca_api_id" type="text" class="regular-text" value="<?php echo esc_attr( $settings['api_id'] ); ?>" /></td></tr>
            <tr><th scope="row"><label for="cca_api_token"><?php esc_html_e( 'API Token', 'custom-checkout-algeria' ); ?></label></th>
                <td><input name="cca[api_token]" id="cca_api_token" type="password" class="regular-text" value="<?php echo esc_attr( $settings['api_token'] ); ?>" autocomplete="off" />
                    <p class="description">Depuis Yalidine Developer Dashboard. Testés à l’enregistrement.</p></td></tr>
            <tr><th scope="row"><label for="cca_from_wilaya_id"><?php esc_html_e( 'Wilaya d\'expédition', 'custom-checkout-algeria' ); ?></label></th>
                <td>
                    <select name="cca[from_wilaya_id]" id="cca_from_wilaya_id">
                        <option value=""><?php esc_html_e( '— Sélectionner —', 'custom-checkout-algeria' ); ?></option>
                        <?php foreach ( $wilayas as $w ) : ?>
                            <option value="<?php echo esc_attr( $w['id'] ); ?>" data-name="<?php echo esc_attr( $w['name'] ); ?>" <?php selected( (string) $settings['from_wilaya_id'], (string) $w['id'] ); ?>><?php echo esc_html( $w['name'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(empty($wilayas)) echo '<p class="description" style="color:#d63638;">Saisissez d’abord API ID/Token puis Enregistrer pour charger les wilayas.</p>'; ?>
                    <input type="hidden" name="cca[from_wilaya_name]" id="cca_from_wilaya_name" value="<?php echo esc_attr( $settings['from_wilaya_name'] ); ?>" />
                </td></tr>
            <tr><th scope="row"><?php esc_html_e( 'Modes de livraison', 'custom-checkout-algeria' ); ?></th>
                <td>
                    <label><input type="checkbox" name="cca[enable_home]" value="1" <?php checked( $settings['enable_home'], '1' ); ?> /> <?php esc_html_e( 'Domicile', 'custom-checkout-algeria' ); ?></label><br/>
                    <label><input type="checkbox" name="cca[enable_stopdesk]" value="1" <?php checked( $settings['enable_stopdesk'], '1' ); ?> /> <?php esc_html_e( 'Stop desk', 'custom-checkout-algeria' ); ?></label>
                    <p class="description">Si un seul activé, l’option radio est masquée et auto-sélectionnée.</p>
                </td></tr>
            <tr><th scope="row"><?php esc_html_e( 'Options colis', 'custom-checkout-algeria' ); ?></th>
                <td>
                    <label><input type="checkbox" name="cca[freeshipping]" value="1" <?php checked( $settings['freeshipping'], '1' ); ?> /> <?php esc_html_e( 'Freeshipping (frais payés par expéditeur)', 'custom-checkout-algeria' ); ?></label><br/>
                    <label><input type="checkbox" name="cca[do_insurance]" value="1" <?php checked( $settings['do_insurance'], '1' ); ?> /> <?php esc_html_e( 'Assurance', 'custom-checkout-algeria' ); ?></label>
                </td></tr>
            <tr><th scope="row"><?php esc_html_e( 'Dimensions par défaut', 'custom-checkout-algeria' ); ?></th>
                <td>
                    <p class="description">Utilisées si le produit n’a pas de poids/dimensions. Billable = max(poids réel, L×l×H×0.0002) × qty, surplus >5kg facturé via oversize_fee Yalidine.</p>
                    <label>L <input type="number" name="cca[default_length]" value="<?php echo esc_attr( $settings['default_length'] ); ?>" min="0" class="small-text" /> cm</label>
                    <label>l <input type="number" name="cca[default_width]" value="<?php echo esc_attr( $settings['default_width'] ); ?>" min="0" class="small-text" /> cm</label>
                    <label>H <input type="number" name="cca[default_height]" value="<?php echo esc_attr( $settings['default_height'] ); ?>" min="0" class="small-text" /> cm</label>
                    <label>Poids <input type="number" step="0.1" name="cca[default_weight]" value="<?php echo esc_attr( $settings['default_weight'] ); ?>" min="0" class="small-text" /> kg</label>
                </td></tr>
        </table>
        <?php submit_button( __( 'Enregistrer', 'custom-checkout-algeria' ) ); ?>
        <p>
            <button type="submit" name="cca_clear_cache" value="1" class="button" onclick="return confirm('Vider le cache API ?')"><?php esc_html_e( 'Vider le cache Yalidine', 'custom-checkout-algeria' ); ?></button>
            <span class="description">Wilayas/communes/centres 1h, tarifs 60min.</span>
        </p>
    </form>
    <p class="cca-credit" style="margin-top:2em;color:#646970;">Custom Checkout Algeria — Yalidine express only.</p>
</div>
<script>
(function($){
  $('#cca_from_wilaya_id').on('change', function(){ $('#cca_from_wilaya_name').val($(this).find('option:selected').data('name')||''); });
})(jQuery);
</script>
