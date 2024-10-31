jQuery(document).on('click', '#sepanet-tansend', function(){
	
	jQuery.ajax({
		url : postsepanet.ajax_url,
		type : 'post',
		data : {
			action : 'post_sepanet_tansend',
			oid : postsepanet.oid,
			sec : postsepanet.sec,
			phone : jQuery('#billing_phone').val(),

		},
		success : function( response ) {
			if(response == 'true')
			{
				jQuery('#sepanet-tansend').text('Tan requested');	
			}
			else
			{

			}			
		}
	});
	return false;
});

