(function($){
  'use strict';
  var current=1;
  function goTo(step){
    current=step;
    $('.cca-wizard__panel').removeClass('is-active');
    $('.cca-wizard__panel[data-panel="'+step+'"]').addClass('is-active');
    $('.cca-wizard__steps li').removeClass('is-active is-done');
    $('.cca-wizard__steps li').each(function(){
      var s=parseInt($(this).data('step'),10);
      if(s<step) $(this).addClass('is-done');
      else if(s===step) $(this).addClass('is-active');
    });
  }
  function feedback(step,msg,isError){
    var $el=$('[data-feedback="'+step+'"]');
    $el.text(msg||'').toggleClass('is-error',!!isError).toggleClass('is-ok',!isError&&!!msg);
  }
  $(document).on('click','.cca-wizard-next', function(){ goTo(current+1); });
  $(document).on('click','.cca-wizard-prev', function(){ goTo(Math.max(1,current-1)); });
  $(document).on('click','.cca-wizard-save-credentials', function(){
    var $btn=$(this); $btn.prop('disabled',true); feedback(2,'');
    $.post(ccaWizard.ajaxUrl, {action:'cca_wizard_step', nonce:ccaWizard.nonce, step:'credentials', api_id:$('#cca_api_id').val(), api_token:$('#cca_api_token').val()})
      .done(function(res){
        if(!res.success){ feedback(2,(res.data&&res.data.message)||'Erreur',true); return; }
        feedback(2,res.data.message,false);
        var $select=$('#cca_from_wilaya').empty().append('<option value="">— Sélectionner —</option>');
        (res.data.wilayas||[]).forEach(function(w){ $select.append($('<option/>').val(w.id).text(w.name)); });
        goTo(3);
      }).fail(function(){ feedback(2,'Erreur réseau',true); }).always(function(){ $btn.prop('disabled',false); });
  });
  $(document).on('click','.cca-wizard-save-sender', function(){
    var $opt=$('#cca_from_wilaya option:selected'), $btn=$(this); $btn.prop('disabled',true); feedback(3,'');
    $.post(ccaWizard.ajaxUrl, {action:'cca_wizard_step', nonce:ccaWizard.nonce, step:'sender', from_wilaya_id:$opt.val(), from_wilaya_name:$opt.text()})
      .done(function(res){ if(!res.success){ feedback(3,(res.data&&res.data.message)||'Erreur',true); return; } goTo(4); })
      .fail(function(){ feedback(3,'Erreur réseau',true); }).always(function(){ $btn.prop('disabled',false); });
  });
  $(document).on('click','.cca-wizard-save-defaults', function(){
    var $btn=$(this); $btn.prop('disabled',true); feedback(4,'');
    $.post(ccaWizard.ajaxUrl, {action:'cca_wizard_step', nonce:ccaWizard.nonce, step:'defaults', enable_home:$('#cca_enable_home').is(':checked')?1:0, enable_stopdesk:$('#cca_enable_stopdesk').is(':checked')?1:0, freeshipping:$('#cca_freeshipping').is(':checked')?1:0, do_insurance:$('#cca_do_insurance').is(':checked')?1:0, default_length:$('#cca_default_length').val(), default_width:$('#cca_default_width').val(), default_height:$('#cca_default_height').val(), default_weight:$('#cca_default_weight').val()})
      .done(function(res){ if(!res.success){ feedback(4,(res.data&&res.data.message)||'Erreur',true); return; } goTo(5); })
      .fail(function(){ feedback(4,'Erreur réseau',true); }).always(function(){ $btn.prop('disabled',false); });
  });
  $(document).on('click','.cca-wizard-finish', function(){
    var $btn=$(this); $btn.prop('disabled',true);
    $.post(ccaWizard.ajaxUrl, {action:'cca_wizard_step', nonce:ccaWizard.nonce, step:'finish'}).always(function(){ window.location.href=ccaWizard.settingsUrl; });
  });
})(jQuery);
