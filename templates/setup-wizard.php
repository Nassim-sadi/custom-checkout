<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap cca-wizard-wrap">
    <div class="cca-wizard">
        <header class="cca-wizard__header">
            <h1><?php esc_html_e( 'Configuration Yalidine', 'custom-checkout-algeria' ); ?></h1>
            <p><?php esc_html_e( 'Assistant en 3 étapes : API → Wilaya d\'expédition → Options. Express uniquement.', 'custom-checkout-algeria' ); ?></p>
            <ol class="cca-wizard__steps">
                <li class="is-active" data-step="1">Bienvenue</li>
                <li data-step="2">API</li>
                <li data-step="3">Expéditeur</li>
                <li data-step="4">Options</li>
                <li data-step="5">Terminé</li>
            </ol>
        </header>
        <div class="cca-wizard__body">
            <section class="cca-wizard__panel is-active" data-panel="1">
                <h2>Bienvenue</h2>
                <p>Configurez votre compte Yalidine, la wilaya d’expédition et les options (domicile / stop desk) avec choix du stop desk par commune.</p>
                <p>Cache : wilayas/communes/centres 1h, tarifs 60min, calcul billable + overweight >5kg.</p>
                <button type="button" class="button button-primary cca-wizard-next">Commencer</button>
            </section>
            <section class="cca-wizard__panel" data-panel="2">
                <h2>Identifiants API</h2>
                <p>Générez API ID/Token depuis le Developer Dashboard Yalidine.</p>
                <p class="cca-field"><label for="cca_api_id">API ID</label><input type="text" id="cca_api_id" class="regular-text" autocomplete="off" /></p>
                <p class="cca-field"><label for="cca_api_token">API Token</label><input type="password" id="cca_api_token" class="regular-text" autocomplete="off" /></p>
                <p class="cca-wizard__feedback" data-feedback="2"></p>
                <p><button type="button" class="button cca-wizard-prev">Retour</button> <button type="button" class="button button-primary cca-wizard-save-credentials">Tester et continuer</button></p>
            </section>
            <section class="cca-wizard__panel" data-panel="3">
                <h2>Wilaya d’expédition</h2>
                <p>Choisissez la wilaya depuis laquelle vous expédiez.</p>
                <p class="cca-field"><label for="cca_from_wilaya">Wilaya</label><select id="cca_from_wilaya" class="regular-text"><option value="">— Sélectionner —</option></select></p>
                <p class="cca-wizard__feedback" data-feedback="3"></p>
                <p><button type="button" class="button cca-wizard-prev">Retour</button> <button type="button" class="button button-primary cca-wizard-save-sender">Continuer</button></p>
            </section>
            <section class="cca-wizard__panel" data-panel="4">
                <h2>Options</h2>
                <label class="cca-check"><input type="checkbox" id="cca_enable_home" checked /><span>Domicile</span></label>
                <label class="cca-check"><input type="checkbox" id="cca_enable_stopdesk" checked /><span>Stop desk (avec choix du centre par commune)</span></label>
                <label class="cca-check"><input type="checkbox" id="cca_freeshipping" checked /><span>Freeshipping (frais payés par expéditeur)</span></label>
                <label class="cca-check"><input type="checkbox" id="cca_do_insurance" /><span>Assurance</span></label>
                <p><strong>Dimensions par défaut</strong> (si produit sans poids/dims)</p>
                <div class="cca-dims">
                    <div><label for="cca_default_length">L (cm)</label><input type="number" id="cca_default_length" value="10" min="0" class="small-text" /></div>
                    <div><label for="cca_default_width">l (cm)</label><input type="number" id="cca_default_width" value="10" min="0" class="small-text" /></div>
                    <div><label for="cca_default_height">H (cm)</label><input type="number" id="cca_default_height" value="10" min="0" class="small-text" /></div>
                    <div><label for="cca_default_weight">Poids (kg)</label><input type="number" id="cca_default_weight" value="1" min="0" step="0.1" class="small-text" /></div>
                </div>
                <p class="cca-wizard__feedback" data-feedback="4"></p>
                <p><button type="button" class="button cca-wizard-prev">Retour</button> <button type="button" class="button button-primary cca-wizard-save-defaults">Continuer</button></p>
            </section>
            <section class="cca-wizard__panel" data-panel="5">
                <h2>C’est prêt !</h2>
                <p>Testez le checkout : wilaya → commune → (si stop desk) choix du centre → frais mis à jour (express + overweight).</p>
                <p><button type="button" class="button button-primary cca-wizard-finish">Terminer</button></p>
            </section>
        </div>
    </div>
</div>
