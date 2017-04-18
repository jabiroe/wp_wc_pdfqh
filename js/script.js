jQuery(document).ready(function($) {
	$("#doaction, #doaction2").click(function (event) {
		var actionselected = $(this).attr("id").substr(2);
		var action = $('select[name="' + actionselected + '"]').val();
		if ( $.inArray(action, wp_wc_pdfqh_ajax.bulk_actions) !== -1 ) {
			event.preventDefault();
			var template = action;
			var checked = [];
			$('tbody th.check-column input[type="checkbox"]:checked').each(
				function() {
					checked.push($(this).val());
				}
			);
			
			if (!checked.length) {
				alert('You have to select order(s) first!');
				return;
			}
			
			var order_ids=checked.join('x');

			if (wp_wc_pdfqh_ajax.ajaxurl.indexOf("?") != -1) {
				url = wp_wc_pdfqh_ajax.ajaxurl+'&action=generate_wp_wc_pdfqh&template_type='+template+'&order_ids='+order_ids+'&_wpnonce='+wp_wc_pdfqh_ajax.nonce;
			} else {
				url = wp_wc_pdfqh_ajax.ajaxurl+'?action=generate_wp_wc_pdfqh&template_type='+template+'&order_ids='+order_ids+'&_wpnonce='+wp_wc_pdfqh_ajax.nonce;
			}

			window.open(url,'_blank');
		}
	});

	$('#wp_wc_pdfqh-data-input-box').insertAfter('#woocommerce-order-data');

	// enable quote number edit if user initiated
	$('#wp_wc_pdfqh-data-input-box label').click(function (event) {
		input = $(this).attr('for');
		$('#'+input).prop('disabled', false);
	});
	$( "#_pdfqh_quote_number" ).on( "click", function() {
		console.log( this );
	});
});

