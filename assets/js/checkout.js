(function($){
  'use strict';
  if(typeof ccaCheckout==='undefined') return;

  var cfg = ccaCheckout;
  var TTL = 60 * 60 * 1000;

  var state = {
    wilayasPopulated: false,
    restoring: false,
    centersFor: {},        // wilayaId -> { centers: [] }
    lastFeeKey: '',
    feeTimer: null,
    updating: false,
    xhr: { communes: null, centers: null, fee: null }
  };

  function $wilaya(){ return $('#billing_wilaya'); }
  function $commune(){ return $('#billing_commune'); }
  function $stopdeskBox(){ return $('#cca-stopdesk-box'); }

  /* ---------------------------------------------------------------- storage */
  function cacheGet(key){
    try{
      var raw = window.sessionStorage.getItem('cca:' + key);
      if(!raw) return null;
      var o = JSON.parse(raw);
      if(!o || !o.t || (Date.now() - o.t) > TTL) return null;
      return o.d;
    }catch(e){ return null; }
  }
  function cacheSet(key, data){
    try{ window.sessionStorage.setItem('cca:' + key, JSON.stringify({ t: Date.now(), d: data })); }catch(e){}
  }

  /* ---------------------------------------------------------------- helpers */
  function api(action, data){
    data = data || {};
    data.action = action;
    data.nonce = cfg.nonce;
    return $.post(cfg.ajaxUrl, data);
  }

  function isApiMode(){ return !!(cfg.wilayas && cfg.wilayas.length); }

  function selectedWilayaId(){
    var fromHidden = $('#cca_wilaya_id').val();
    if(fromHidden) return parseInt(fromHidden, 10);
    var fromData = $wilaya().find('option:selected').data('id');
    if(fromData) return parseInt(fromData, 10);
    var val = $wilaya().val();
    if(val && !isNaN(val)) return parseInt(val, 10);
    return 0;
  }

  function selectedCommuneId(){
    var hidden = $('#cca_commune_id').val();
    if(hidden) return parseInt(hidden, 10);
    var fromData = $commune().find('option:selected').data('id');
    if(fromData) return parseInt(fromData, 10);
    return 0;
  }

  // Radios use `home` / `desk`, but the server session stores `stopdesk`.
  // Always compare against the radio vocabulary.
  function normalizeDelivery(value){
    return (value === 'desk' || value === 'stopdesk') ? 'desk' : 'home';
  }

  function selectedDelivery(){
    return normalizeDelivery($('input[name="billing_delivery_type"]:checked').val());
  }

  function triggerUpdate(){
    if(state.updating) return;
    state.updating = true;
    $(document.body).trigger('update_checkout');
    setTimeout(function(){ state.updating = false; }, 600);
  }

  function setStopdeskValue(id, name){
    $('#cca_stopdesk_id').val(id || '');
    $('#cca_stopdesk_name').val(name || '');
    $('#cca_stopdesk_list .cca-delivery-option').removeClass('is-selected');
    if(id){
      $('#cca_stopdesk_list input[value="'+id+'"]')
        .closest('.cca-delivery-option').addClass('is-selected');
    }
  }

  function ensureHiddenIds(){
    var $form = $('form.checkout');
    if(!$form.length) $form = $('form.woocommerce-checkout');
    if(!$form.length) return;
    if(!$('#cca_wilaya_id').length) $form.append('<input type="hidden" id="cca_wilaya_id" name="cca_wilaya_id" value="" />');
    if(!$('#cca_wilaya_name').length) $form.append('<input type="hidden" id="cca_wilaya_name" name="cca_wilaya_name" value="" />');
    if(!$('#cca_commune_id').length) $form.append('<input type="hidden" id="cca_commune_id" name="cca_commune_id" value="" />');
    if(!$('#cca_fee').length) $form.append('<input type="hidden" id="cca_fee" name="cca_fee" value="" />');
    if(!$('#cca_stopdesk_id').length) $form.append('<input type="hidden" id="cca_stopdesk_id" name="cca_stopdesk_id" value="" />');
    if(!$('#cca_stopdesk_name').length) $form.append('<input type="hidden" id="cca_stopdesk_name" name="cca_stopdesk_name" value="" />');
  }

  /* ------------------------------------------------------------- wilayas UI */
  function populateWilayas(){
    if(state.wilayasPopulated) return;
    var $w = $wilaya();
    if(!$w.length) return;
    if(isApiMode()){
      var cur = $w.val();
      var curId = $w.find('option:selected').data('id') || $('#cca_wilaya_id').val() || cur;
      $w.empty().append($('<option/>').val('').text(cfg.i18n.selectWilaya));
      cfg.wilayas.forEach(function(w){
        $w.append($('<option/>').val(w.id).text(w.name).attr('data-id', w.id));
      });
      if(curId) $w.val(curId);
      state.wilayasPopulated = true;
    } else if(typeof algeriaCities !== 'undefined'){
      if($w.find('option').length <= 1){
        algeriaCities.wilayas.forEach(function(w){
          $w.append($('<option/>').val(w.name).text(w.name).attr('data-id', w.id));
        });
      }
      state.wilayasPopulated = true;
    }
  }

  /* ------------------------------------------------------------ communes UI */
  function fillCommunes(list){
    var $field = $commune();
    $field.empty().append($('<option/>').val('').text(cfg.i18n.selectCommune));
    (list || []).forEach(function(c){
      $field.append($('<option/>')
        .val(c.name)
        .text(c.name)
        .attr('data-id', c.id)
        .attr('data-has-stop-desk', c.has_stop_desk ? '1' : '0'));
    });
  }

  function resetCommunes(){
    $commune().prop('disabled', false).empty()
      .append($('<option/>').val('').text(cfg.i18n.selectCommune));
    $('#cca_commune_id').val('');
    resetCenters();
    clearFee();
  }

  function loadCommunes(wilayaId, keepSelection){
    var $field = $commune();
    if(!wilayaId){ resetCommunes(); triggerUpdate(); return; }

    // Static fallback path – no API credentials at all.
    if(!isApiMode()){
      if(typeof algeriaCities !== 'undefined' && algeriaCities.communes[wilayaId]){
        fillCommunes(algeriaCities.communes[wilayaId]);
      } else {
        fillCommunes([]);
      }
      if(keepSelection) applyCommuneSelection();
      return;
    }

    var cached = cacheGet('communes:' + wilayaId);
    if(cached){
      fillCommunes(cached);
      if(keepSelection) applyCommuneSelection();
      return;
    }

    if(state.xhr.communes) state.xhr.communes.abort();
    $field.prop('disabled', true).empty().append($('<option/>').val('').text(cfg.i18n.loading));

    state.xhr.communes = api('cca_get_communes', { wilaya_id: wilayaId })
      .done(function(res){
        $field = $commune().prop('disabled', false);
        if(!res.success){
          if(typeof algeriaCities !== 'undefined' && algeriaCities.communes[wilayaId]){
            fillCommunes(algeriaCities.communes[wilayaId]);
          }
          return;
        }
        var list = res.data.communes || [];
        fillCommunes(list);
        cacheSet('communes:' + wilayaId, list);
      })
      .fail(function(){
        $field = $commune().prop('disabled', false);
        if(typeof algeriaCities !== 'undefined' && algeriaCities.communes[wilayaId]){
          fillCommunes(algeriaCities.communes[wilayaId]);
        }
      })
      .always(function(){
        if(keepSelection) applyCommuneSelection();
        state.xhr.communes = null;
      });
  }

  function applyCommuneSelection(){
    var want = (cfg.restore && cfg.restore.commune_id) ? parseInt(cfg.restore.commune_id, 10) : 0;
    if(!want) return;
    var $opt = $commune().find('option[data-id="'+want+'"]');
    if($opt.length){
      $commune().val($opt.val());
      $('#cca_commune_id').val(want);
    }
  }

  /* ------------------------------------------------------------ centers UI */
  function resetCenters(){
    var $list = $('#cca_stopdesk_list');
    if($list.length) $list.html('<p class="cca-stopdesk-empty">' + cfg.i18n.selectCenter + '</p>');
    setStopdeskValue('', '');
    $stopdeskBox().hide();
  }

  function renderCenters(centers, opts){
    opts = opts || {};
    var $list = $('#cca_stopdesk_list');
    $list.empty();
    if(!centers || !centers.length){
      $list.html(
        '<p class="cca-stopdesk-empty">' + (cfg.i18n.noCenterInCommune || 'Aucun stop desk dans cette commune.') + '</p>' +
        (cfg.enableHome ? '<p class="cca-stopdesk-hint">' + (cfg.i18n.useHome || 'Choisissez la livraison à domicile.') + '</p>' : '')
      );
      setStopdeskValue('', '');
      return;
    }
    centers.forEach(function(c){
      var meta = [];
      if(c.address) meta.push(c.address);
      // In fallback mode the badge carries the commune, so don't repeat it in the
      // meta line. Otherwise the meta stays as before.
      if(!opts.showCommune && c.commune_name) meta.push(c.commune_name);
      var $card = $('<label/>', { 'class': 'cca-delivery-option cca-stopdesk-card' });
      $card.append($('<input/>', {
        type: 'radio',
        name: 'cca_stopdesk_choice',
        value: c.id,
        'data-name': c.name,
        'data-commune': c.commune_id || ''
      }));
      var $body = $('<span/>', { 'class': 'cca-delivery-option__body' });
      var $label = $('<span/>', { 'class': 'cca-delivery-option__label', text: c.name });
      if(opts.showCommune && c.commune_name){
        $label.append($('<span/>', { 'class': 'cca-stopdesk-badge', text: c.commune_name }));
      }
      $body.append($label);
      if(meta.length){
        $body.append($('<span/>', { 'class': 'cca-delivery-option__meta', text: meta.join(' · ') }));
      }
      $card.append($body);
      $list.append($card);
    });

    // Selection is applied by the caller (see applyPlanSelection).
    setStopdeskValue('', '');
  }

  function loadCenters(){
    var wid = selectedWilayaId();
    var cid = selectedCommuneId();
    var $list = $('#cca_stopdesk_list');
    if(!wid || !$list.length || selectedDelivery() !== 'desk'){ resetCenters(); return; }
    var cached = state.centersFor[wid];
    if(cached){
      paintCenters(cached, cid);
      return;
    }

    var sessionCached = cacheGet('centers:' + wid);
    if(sessionCached){
      state.centersFor[wid] = sessionCached;
      paintCenters(sessionCached, cid);
      return;
    }

    if(state.xhr.centers) state.xhr.centers.abort();
    $list.html('<p class="cca-stopdesk-empty">' + cfg.i18n.loading + '</p>');

    state.xhr.centers = api('cca_get_centers', { wilaya_id: wid, commune_id: cid })
      .done(function(res){
        var payload = (res.success && res.data) ? { centers: res.data.centers || [] } : null;
        if(payload){
          state.centersFor[wid] = payload;
          cacheSet('centers:' + wid, payload);
        }
        paintCenters(payload, cid);
      })
      .fail(function(){
        $('#cca_stopdesk_list').html('<p class="cca-stopdesk-empty">' + cfg.i18n.selectCenter + '</p>');
        setStopdeskValue('', '');
      })
      .always(function(){ state.xhr.centers = null; });
  }

  /**
   * Resolve which desks the customer may pick for the chosen commune, render
   * them, then apply the selection.
   *
   * The partitioning lives in assets/js/cca-desks.js so it is unit testable
   * without a DOM; this only paints what that returns.
   */
  function paintCenters(payload, communeId){
    var $list = $('#cca_stopdesk_list');
    if(!payload){ $list.empty(); setStopdeskValue('', ''); return; }

    var plan = ccaPlanDesks.planDesks(
      payload.centers || [],
      communeId,
      cfg.restore && cfg.restore.stopdesk_id
    );

    if(plan.mode === 'wilaya-empty'){
      $list.empty();
      $list.html(
        '<p class="cca-stopdesk-empty">' + (cfg.i18n.noCenterInWilaya || 'Aucun stop desk dans cette wilaya.') + '</p>' +
        (cfg.enableHome ? '<p class="cca-stopdesk-hint">' + (cfg.i18n.useHome || 'Choisissez la livraison à domicile.') + '</p>' : '')
      );
      setStopdeskValue('', '');
      return;
    }

    renderCenters(plan.offered, { showCommune: plan.mode === 'fallback' });

    if(plan.mode === 'fallback'){
      $list.prepend($('<p/>', {
        'class': 'cca-stopdesk-note',
        text: cfg.i18n.notInYourCommune || 'Pas de stop desk dans votre commune. Choisissez parmi les bureaux de la wilaya :'
      }));
    }

    applyPlanSelection(plan);
  }

  // A desk restored from the session wins, otherwise the single offered desk is
  // taken. With more than one on offer nothing is preselected and the customer
  // must choose (validate_fields blocks submission until they do).
  function applyPlanSelection(plan){
    if(!plan.selected) return;
    var $inp = $('#cca_stopdesk_list').find('input[value="'+plan.selected+'"]');
    if(!$inp.length) return;
    $inp.prop('checked', true);
    setStopdeskValue(plan.selected, $inp.data('name') || plan.selectedName || '');
  }

  // Warm the centre cache as soon as a wilaya is picked, so toggling to
  // stop desk is instant and costs no extra request.
  function prefetchCenters(wilayaId){
    if(!cfg.enableStopdesk || !wilayaId) return;
    if(state.centersFor[wilayaId]) return;
    var cached = cacheGet('centers:' + wilayaId);
    if(cached){ state.centersFor[wilayaId] = cached; return; }
    api('cca_get_centers', { wilaya_id: wilayaId, commune_id: 0 })
      .done(function(res){
        if(res.success && res.data){
          var payload = { centers: res.data.centers || [] };
          state.centersFor[wilayaId] = payload;
          cacheSet('centers:' + wilayaId, payload);
          // If the customer switched to desk while we were fetching, paint now.
          if(selectedDelivery() === 'desk' && selectedWilayaId() === wilayaId){
            paintCenters(payload, selectedCommuneId());
          }
        }
      });
  }

  /* --------------------------------------------------------------- fees UI */
  function clearFee(){
    $('#cca_fee').val('');
    state.lastFeeKey = '';
  }

  function refreshFee(){
    var wid = selectedWilayaId();
    var cid = selectedCommuneId();
    var delivery = selectedDelivery();
    var key = wid + '|' + cid + '|' + delivery;

    if(!wid || !cid){ clearFee(); triggerUpdate(); return; }

    // Nothing changed since the last calculation – skip the round trip.
    if(key === state.lastFeeKey && $('#cca_fee').val() !== ''){ return; }
    state.lastFeeKey = key;

    if(state.feeTimer) clearTimeout(state.feeTimer);
    state.feeTimer = setTimeout(function(){
      if(state.xhr.fee) state.xhr.fee.abort();
      state.xhr.fee = api('cca_get_fee', {
        wilaya_id: wid,
        commune_id: cid,
        delivery_type: delivery
      }).done(function(res){
        if(!res.success){
          clearFee();
          triggerUpdate();
          return;
        }
        $('#cca_fee').val(res.data.fee);
        triggerUpdate();
      }).fail(function(xhr, status){
        if(status === 'abort') return;
        clearFee();
        triggerUpdate();
      }).always(function(){ state.xhr.fee = null; });
    }, 250);
  }

  function toggleStopdesk(){
    var isDesk = selectedDelivery() === 'desk';
    if($('#cca-stopdesk-box').length){
      if(isDesk) $stopdeskBox().show();
      else $stopdeskBox().hide();
    }
    $('.custom-radio-group .radio-option-card').removeClass('is-selected');
    $('.custom-radio-group .radio-option-card').has('input:checked').addClass('is-selected');
    if(isDesk && selectedWilayaId() && selectedCommuneId()){
      loadCenters();
    } else if(!isDesk){
      resetCenters();
    }
    refreshFee();
  }

  /* ------------------------------------------------------------------ init */
  function restoreState(){
    var r = cfg.restore;
    if(!r || !r.wilaya_id) return false;

    ensureHiddenIds();

    var $w = $wilaya();
    if($w.length && !$w.val()){
      $w.val(String(r.wilaya_id));
      if(!$w.val()) $w.find('option[data-id="'+r.wilaya_id+'"]').prop('selected', true);
    }
    $('#cca_wilaya_id').val(r.wilaya_id);
    $('#cca_wilaya_name').val(r.wilaya_name || $w.find('option:selected').text() || '');

    state.restoring = true;

    var cid = r.commune_id;
    if(cid){
      loadCommunes(r.wilaya_id, true);
    }

    var delivery = normalizeDelivery(r.delivery) || 'desk';
    var $radios = $('input[name="billing_delivery_type"]');
    if(!$radios.length) return false;
    // `desk` may be unavailable if stop desk is disabled in settings.
    if(!$radios.filter('[value="'+delivery+'"]').length){
      delivery = $radios.filter(':checked').val() || $radios.first().val();
    }
    $radios.filter('[value="'+delivery+'"]').prop('checked', true);
    $('.custom-radio-group .radio-option-card').removeClass('is-selected');
    $('.custom-radio-group .radio-option-card').has($radios.filter(':checked')).addClass('is-selected');

    if(delivery === 'desk'){
      $stopdeskBox().show();
      if(cid) loadCenters();
    } else {
      $stopdeskBox().hide();
    }

    // Fee already lives in the session – no need to ask the API again.
    if(r.fee > 0) $('#cca_fee').val(r.fee);

    setTimeout(function(){
      state.restoring = false;
      if(cid && delivery === 'desk') paintFromCache();
    }, 300);
    return true;
  }

  function paintFromCache(){
    var wid = selectedWilayaId();
    var payload = state.centersFor[wid];
    if(payload) paintCenters(payload, selectedCommuneId());
  }

  function wrapRadioButtons(){
    $('.custom-radio-group .woocommerce-input-wrapper').each(function(){
      if($(this).find('.radio-option-card').length === 0){
        $(this).find('input[type="radio"]').each(function(){
          var $input = $(this);
          var $label = $input.next('label');
          var $card = $input.add($label).wrapAll('<div class="radio-option-card"></div>').parent();
          $card.on('click', function(){
            $input.prop('checked', true).trigger('change');
            $(document.body).trigger('update_checkout');
          });
        });
      }
    });
  }

  $(document).ready(function(){
    if(!$wilaya().length) return;

    ensureHiddenIds();
    populateWilayas();
    wrapRadioButtons();

    $(document.body).on('updated_checkout', function(){
      wrapRadioButtons();
      ensureHiddenIds();
    });

    // Back/forward navigation keeps the DOM but loses our handlers' state.
    $(window).on('pageshow', function(){
      ensureHiddenIds();
      restoreState();
    });

    $wilaya().on('change', function(){
      var $opt = $(this).find('option:selected');
      var wid = $opt.data('id') || $(this).val();
      ensureHiddenIds();
      $('#cca_wilaya_id').val(wid);
      $('#cca_wilaya_name').val($opt.text() || $(this).val());
      $('#cca_commune_id').val('');
      setStopdeskValue('', '');
      clearFee();
      loadCommunes(wid, false);
      prefetchCenters(parseInt(wid, 10));
      triggerUpdate();
    });

    $commune().on('change', function(){
      var $opt = $(this).find('option:selected');
      var cid = $opt.data('id') || '';
      $('#cca_commune_id').val(cid);
      if(selectedDelivery() === 'desk') loadCenters();
      refreshFee();
    });

    $(document.body).on('change', 'input[name="cca_stopdesk_choice"]', function(){
      setStopdeskValue($(this).val(), $(this).data('name') || '');
    });

    $(document.body).on('change', 'input[name="billing_delivery_type"]', function(){
      toggleStopdesk();
    });

    var $phone = $('#billing_phone');
    if($phone.length){
      $phone.on('input', function(){
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
      });
    }

    $('form.checkout').on('checkout_place_order', function(){
      ensureHiddenIds();
      var phone = $phone.val();
      if(!/^(05|06|07)[0-9]{8}$/.test(phone)){
        alert('Le numéro de téléphone doit être un numéro algérien valide (ex: 05XXXXXXXX).');
        return false;
      }
      if(!$('#cca_wilaya_id').val()){
        var wid = $wilaya().find('option:selected').data('id') || $wilaya().val();
        $('#cca_wilaya_id').val(wid);
        $('#cca_wilaya_name').val($wilaya().find('option:selected').text());
      }
      return true;
    });

    // Restore before wiring, so handlers see the final state.
    restoreState();
  });
})(jQuery);
