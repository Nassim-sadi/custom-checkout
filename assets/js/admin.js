(function($){
  'use strict';
  if(typeof ccaAdmin==='undefined') return;
  // Settings wilaya name sync handled inline; admin parcel button
  $(document).on('click','.cca-send-parcel', function(e){
    e.preventDefault();
    if(!window.confirm(ccaAdmin.i18n.confirm)) return;
    var $wrap=$(this).closest('.cca-order-actions');
    var orderId=$wrap.data('order-id');
    var $btn=$wrap.find('.cca-send-parcel');
    var $spinner=$wrap.find('.cca-spinner');
    var $msg=$wrap.find('.cca-msg');
    $btn.prop('disabled',true); $spinner.addClass('is-active'); $msg.hide().removeClass('notice-error notice-success');
    $.post(ccaAdmin.ajaxUrl, {action:'cca_send_parcel', nonce:ccaAdmin.nonce, order_id:orderId})
      .done(function(res){
        if(!res.success){ $msg.addClass('notice-error').text((res.data&&res.data.message)||ccaAdmin.i18n.error).show(); $btn.prop('disabled',false); return; }
        var html='<p><strong>Tracking:</strong> <code>'+ $('<div/>').text(res.data.tracking).html() +'</code></p>';
        if(res.data.label) html+='<p><a class="button button-small" href="'+res.data.label+'" target="_blank" rel="noopener">Télécharger bordereau</a></p>';
        $wrap.html(html);
      }).fail(function(){ $msg.addClass('notice-error').text(ccaAdmin.i18n.error).show(); $btn.prop('disabled',false); })
      .always(function(){ $spinner.removeClass('is-active'); });
  });
})(jQuery);
