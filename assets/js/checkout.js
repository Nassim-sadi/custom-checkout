(function($){
  'use strict';
  if(typeof ccaCheckout==='undefined') return;
  var updating=false, feeRequest=null, wilayasPopulated=false;

  function $wilaya(){ return $('#billing_wilaya'); }
  function $commune(){ return $('#billing_commune'); }
  function $stopdeskBox(){ return $('#cca-stopdesk-box'); }

  function setStopdeskValue(id,name){
    $('#cca_stopdesk_id').val(id||'');
    $('#cca_stopdesk_name').val(name||'');
    $('#cca_stopdesk_list .cca-delivery-option').removeClass('is-selected');
    if(id) $('#cca_stopdesk_list input[value="'+id+'"]').closest('.cca-delivery-option').addClass('is-selected');
  }

  function selectedDelivery(){
    return $('input[name="billing_delivery_type"]:checked').val()||'home';
  }

  function triggerUpdate(){
    if(updating) return;
    updating=true;
    $(document.body).trigger('update_checkout');
    setTimeout(function(){ updating=false; }, 600);
  }

  function ensureHiddenIds(){
    // Re-create hidden inputs if Woo replaced form HTML on updated_checkout
    var $form=$('form.checkout');
    if(!$form.length) $form=$('form.woocommerce-checkout');
    if(!$form.length) return;
    if(!$('#cca_wilaya_id').length) $form.append('<input type="hidden" id="cca_wilaya_id" name="cca_wilaya_id" value="" />');
    if(!$('#cca_wilaya_name').length) $form.append('<input type="hidden" id="cca_wilaya_name" name="cca_wilaya_name" value="" />');
    if(!$('#cca_commune_id').length) $form.append('<input type="hidden" id="cca_commune_id" name="cca_commune_id" value="" />');
    if(!$('#cca_fee').length) $form.append('<input type="hidden" id="cca_fee" name="cca_fee" value="" />');
    if(!$('#cca_stopdesk_id').length) $form.append('<input type="hidden" id="cca_stopdesk_id" name="cca_stopdesk_id" value="" />');
    if(!$('#cca_stopdesk_name').length) $form.append('<input type="hidden" id="cca_stopdesk_name" name="cca_stopdesk_name" value="" />');
  }

  function resetCommunes(){
    var $c=$commune();
    $c.prop('disabled',false).empty().append($('<option/>').val('').text(ccaCheckout.i18n.selectCommune));
    $('#cca_commune_id').val('');
    resetCenters();
    clearFee();
  }

  function resetCenters(){
    var $list=$('#cca_stopdesk_list');
    if($list.length) $list.html('<p class="cca-stopdesk-empty">'+ccaCheckout.i18n.selectCenter+'</p>');
    setStopdeskValue('','');
    $stopdeskBox().hide();
  }

  function clearFee(){
    $('#cca_fee').val('');
  }

  function toggleStopdesk(){
    var isDesk = selectedDelivery()==='desk';
    if($('#cca-stopdesk-box').length){
      if(isDesk) $stopdeskBox().show();
      else $stopdeskBox().hide();
    }
    $('.custom-radio-group .radio-option-card').removeClass('is-selected');
    $('.custom-radio-group .radio-option-card').has('input:checked').addClass('is-selected');
    if(isDesk && $wilaya().val() && $('#cca_commune_id').val()){
      loadCenters();
    }
    refreshFee();
  }

  function loadCommunes(wilayaId){
    var $field=$commune();
    resetCommunes();
    if(!wilayaId){ triggerUpdate(); return; }
    // If API wilayas empty, use static fallback immediately
    var hasApi = ccaCheckout.wilayas && ccaCheckout.wilayas.length>0;
    if(!hasApi){
      if(typeof algeriaCities!=='undefined' && algeriaCities.communes[wilayaId]){
        algeriaCities.communes[wilayaId].forEach(function(c){
          $field.append($('<option/>').val(c.name).text(c.name).attr('data-id',c.id));
        });
        return;
      }
      // No API and no static data for this wilaya – still try API as last resort
    }
    $field.prop('disabled',true).empty().append($('<option/>').val('').text(ccaCheckout.i18n.loading));
    $.post(ccaCheckout.ajaxUrl, {action:'cca_get_communes', nonce:ccaCheckout.nonce, wilaya_id:wilayaId}).done(function(res){
      $field=$commune();
      $field.empty().append($('<option/>').val('').text(ccaCheckout.i18n.selectCommune));
      if(!res.success){
        // Fallback to static on API error
        if(typeof algeriaCities!=='undefined' && algeriaCities.communes[wilayaId]){
          algeriaCities.communes[wilayaId].forEach(function(c){
            $field.append($('<option/>').val(c.name).text(c.name).attr('data-id',c.id));
          });
        }
        $field.prop('disabled',false); return;
      }
      (res.data.communes||[]).forEach(function(c){
        $field.append($('<option/>').val(c.name).text(c.name).attr('data-id',c.id).attr('data-has-stop-desk',c.has_stop_desk?'1':'0'));
      });
      $field.prop('disabled',false);
    }).fail(function(){
      // Fallback to static on network fail
      $field=$commune();
      $field.empty().append($('<option/>').val('').text(ccaCheckout.i18n.selectCommune));
      if(typeof algeriaCities!=='undefined' && algeriaCities.communes[wilayaId]){
        algeriaCities.communes[wilayaId].forEach(function(c){
          $field.append($('<option/>').val(c.name).text(c.name).attr('data-id',c.id));
        });
      }
      $field.prop('disabled',false);
    });
  }

  function loadCenters(){
    var wilayaId=$wilaya().find('option:selected').data('id') || $('#cca_wilaya_id').val() || $wilaya().val();
    var wid = $('#cca_wilaya_id').val() || wilayaId;
    if(!wid || isNaN(wid)){
      var wName=$wilaya().val();
      if(ccaCheckout.wilayas) for(var i=0;i<ccaCheckout.wilayas.length;i++){ if(ccaCheckout.wilayas[i].name===wName) { wid=ccaCheckout.wilayas[i].id; break; } }
      // static wilayas have data-id as id but val is name – already handled
      if(!wid || isNaN(wid)){
        if(typeof algeriaCities!=='undefined'){
          for(var k in algeriaCities.communes){ /* search static wilaya id by name */ }
          algeriaCities.wilayas.forEach(function(w){ if(w.name===$wilaya().val()) wid=w.id; });
        }
      }
    }
    var communeId=$('#cca_commune_id').val();
    var $list=$('#cca_stopdesk_list');
    if(!wid || selectedDelivery()!=='desk' || !$list.length){ resetCenters(); return; }
    setStopdeskValue('','');
    $list.html('<p class="cca-stopdesk-empty">'+ccaCheckout.i18n.loading+'</p>');
    $.post(ccaCheckout.ajaxUrl, {action:'cca_get_centers', nonce:ccaCheckout.nonce, wilaya_id:wid, commune_id:communeId}).done(function(res){
      $list=$('#cca_stopdesk_list');
      $list.empty();
      if(!res.success || !(res.data.centers||[]).length){
        $list.html('<p class="cca-stopdesk-empty">'+ccaCheckout.i18n.selectCenter+'</p>'); return;
      }
      (res.data.centers||[]).forEach(function(c,index){
        var $card=$('<label/>',{class:'cca-delivery-option cca-stopdesk-card'});
        var $input=$('<input/>',{type:'radio', name:'cca_stopdesk_choice', value:c.id, 'data-name':c.name});
        if(index===0) $input.prop('checked',true);
        $card.append($input);
        $card.append($('<span/>',{class:'cca-delivery-option__body'}).append($('<span/>',{class:'cca-delivery-option__label', text:c.name})));
        $list.append($card);
      });
      var $first=$list.find('input[type="radio"]').first();
      if($first.length){ setStopdeskValue($first.val(), $first.data('name')); $first.closest('.cca-delivery-option').addClass('is-selected'); }
    }).fail(function(){
      $('#cca_stopdesk_list').html('<p class="cca-stopdesk-empty">'+ccaCheckout.i18n.selectCenter+'</p>');
      setStopdeskValue('','');
    });
  }

  function refreshFee(){
    var wid = $('#cca_wilaya_id').val() || $wilaya().find('option:selected').data('id') || $wilaya().val();
    var communeId=$('#cca_commune_id').val();
    var delivery=selectedDelivery();
    if(feeRequest && feeRequest.abort) feeRequest.abort();
    if(!wid || !communeId){ $('#cca_fee').val(''); triggerUpdate(); return; }
    if(isNaN(wid)){
      var wName=$wilaya().val();
      if(ccaCheckout.wilayas) for(var i=0;i<ccaCheckout.wilayas.length;i++){ if(ccaCheckout.wilayas[i].name===wName) { wid=ccaCheckout.wilayas[i].id; break; } }
      if(isNaN(wid) && typeof algeriaCities!=='undefined'){
        algeriaCities.wilayas.forEach(function(w){ if(w.name===$wilaya().val()) wid=w.id; });
      }
    }
    feeRequest=$.post(ccaCheckout.ajaxUrl, {action:'cca_get_fee', nonce:ccaCheckout.nonce, wilaya_id:wid, commune_id:communeId, delivery_type:delivery}).done(function(res){
      if(!res.success){ $('#cca_fee').val(''); triggerUpdate(); return; }
      $('#cca_fee').val(res.data.fee);
      triggerUpdate();
    }).fail(function(xhr,status){
      if(status==='abort') return;
      $('#cca_fee').val(''); triggerUpdate();
    });
  }

  function populateWilayas(){
    if(wilayasPopulated) return;
    var $w=$wilaya();
    if(!$w.length) return;
    if(ccaCheckout.wilayas && ccaCheckout.wilayas.length){
      var cur=$w.val();
      var curId = $w.find('option:selected').data('id') || $('#cca_wilaya_id').val() || cur;
      $w.empty().append($('<option/>').val('').text(ccaCheckout.i18n.selectWilaya));
      ccaCheckout.wilayas.forEach(function(w){
        $w.append($('<option/>').val(w.id).text(w.name).attr('data-id',w.id));
      });
      if(curId) $w.val(curId);
      wilayasPopulated=true;
    } else if(typeof algeriaCities!=='undefined'){
      if($w.find('option').length<=1){
        algeriaCities.wilayas.forEach(function(w){
          $w.append($('<option/>').val(w.name).text(w.name).attr('data-id',w.id));
        });
      }
      wilayasPopulated=true;
    }
  }

  function wrapRadioButtons(){
    $('.custom-radio-group .woocommerce-input-wrapper').each(function(){
      if($(this).find('.radio-option-card').length===0){
        $(this).find('input[type="radio"]').each(function(){
          var $input=$(this); var $label=$input.next('label');
          var $card=$input.add($label).wrapAll('<div class="radio-option-card"></div>').parent();
          $card.on('click', function(){ $input.prop('checked',true).trigger('change'); $(document.body).trigger('update_checkout'); });
        });
      }
    });
  }

  $(document).ready(function(){
    var $wilayaSelect=$wilaya(), $communeSelect=$commune(), $phoneInput=$('#billing_phone');
    if(!$wilayaSelect.length) return;

    ensureHiddenIds();
    populateWilayas();
    wrapRadioButtons();
    $(document.body).on('updated_checkout', function(){
      wrapRadioButtons();
      ensureHiddenIds();
    });

    $wilayaSelect.on('change', function(){
      var $opt=$(this).find('option:selected');
      var wilayaId=$opt.data('id') || $(this).val();
      var wilayaName=$opt.text() || $(this).val();
      ensureHiddenIds();
      $('#cca_wilaya_id').val(wilayaId);
      $('#cca_wilaya_name').val(wilayaName);
      // For static fallback, wilayaName is value; ensure commune load uses id
      var hasApi = ccaCheckout.wilayas && ccaCheckout.wilayas.length>0;
      if(!hasApi){
        $communeSelect.empty().append($('<option/>').val('').text(ccaCheckout.i18n.selectCommune));
        if(wilayaId && typeof algeriaCities!=='undefined' && algeriaCities.communes[wilayaId]){
          algeriaCities.communes[wilayaId].forEach(function(c){
            $communeSelect.append($('<option/>').val(c.name).text(c.name).attr('data-id',c.id));
          });
        }
      } else {
        loadCommunes(wilayaId);
      }
      triggerUpdate();
    });

    $communeSelect.on('change', function(){
      var $opt=$(this).find('option:selected');
      var communeId=$opt.data('id')||'';
      $('#cca_commune_id').val(communeId);
      if(selectedDelivery()==='desk') loadCenters();
      refreshFee();
    });

    $(document.body).on('change','input[name="cca_stopdesk_choice"]', function(){
      setStopdeskValue($(this).val(), $(this).data('name')||'');
    });
    $(document.body).on('change','input[name="billing_delivery_type"]', function(){
      toggleStopdesk();
    });

    if($phoneInput.length){
      $phoneInput.on('input', function(){ this.value=this.value.replace(/[^0-9]/g,'').substring(0,10); });
    }
    $('form.checkout').on('checkout_place_order', function(){
      ensureHiddenIds();
      var phone=$phoneInput.val();
      if(!/^(05|06|07)[0-9]{8}$/.test(phone)){ alert('Le numéro de téléphone doit être un numéro algérien valide (ex: 05XXXXXXXX).'); return false; }
      // Ensure wilaya hidden has value before submit
      if(!$('#cca_wilaya_id').val()){
        var wid=$wilaya().find('option:selected').data('id')||$wilaya().val();
        $('#cca_wilaya_id').val(wid);
        $('#cca_wilaya_name').val($wilaya().find('option:selected').text());
      }
      return true;
    });
  });
})(jQuery);
