jQuery(document).ready(function($) {
    $('#create-affiliate-account').on('click', function(e) {
        e.preventDefault();
        
        $.ajax({
            type: 'POST',
            url: goaffpro_ajax.ajax_url,
            data: {
                action: 'create_affiliate_account'
            },
            success: function(response) {
                if (response.success) {
                    $('#create-affiliate-account').replaceWith('<p>' + response.data.message + '</p>');
                } else {
                    $('#affiliate-account-error').text(response.data.message);
                }
            },
            error: function(response) {
                $('#affiliate-account-error').text('An error occurred while creating the affiliate account.');
            }
        });
    });
});
