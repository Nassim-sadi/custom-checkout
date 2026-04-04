(function($) {
    'use strict';

    $(document).ready(function() {
        const $wilayaSelect = $('#billing_wilaya');
        const $communeSelect = $('#billing_commune');
        const $phoneInput = $('#billing_phone');

        if (!$wilayaSelect.length) return;

        // Populate Wilayas
        algeriaCities.wilayas.forEach(function(wilaya) {
            $wilayaSelect.append($('<option>', {
                value: wilaya.name,
                text: wilaya.name,
                'data-id': wilaya.id
            }));
        });

        // Handle Wilaya Change
        $wilayaSelect.on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const wilayaId = selectedOption.data('id');
            
            // Clear Communes
            $communeSelect.empty().append($('<option>', {
                value: '',
                text: 'Sélectionnez votre commune'
            }));

            if (wilayaId && algeriaCities.communes[wilayaId]) {
                algeriaCities.communes[wilayaId].forEach(function(commune) {
                    $communeSelect.append($('<option>', {
                        value: commune.name,
                        text: commune.name
                    }));
                });
            }
            
            // Trigger WC update for checkout
            $(document.body).trigger('update_checkout');
        });

        // Wrap radio buttons for better styling
        function wrapRadioButtons() {
            $('.custom-radio-group .woocommerce-input-wrapper').each(function() {
                if ($(this).find('.radio-option-card').length === 0) {
                    $(this).find('input[type="radio"]').each(function() {
                        const $input = $(this);
                        const $label = $input.next('label');
                        const $card = $input.add($label).wrapAll('<div class="radio-option-card"></div>').parent();
                        
                        // Handle click on the entire card
                        $card.on('click', function() {
                            $input.prop('checked', true).trigger('change');
                            $(document.body).trigger('update_checkout');
                        });
                    });
                }
            });
        }
        wrapRadioButtons();
        $(document.body).on('updated_checkout', wrapRadioButtons);

        // Phone validation (numeric only, max 10)
        $phoneInput.on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        // Frontend validation on checkout submit
        $('form.checkout').on('checkout_place_order', function() {
            const phone = $phoneInput.val();
            const phoneRegex = /^(05|06|07)[0-9]{8}$/;

            if (!phoneRegex.test(phone)) {
                alert('Le numéro de téléphone doit être un numéro algérien valide (ex: 05XXXXXXXX, 06XXXXXXXX, 07XXXXXXXX).');
                return false;
            }
            return true;
        });

    });

})(jQuery);
