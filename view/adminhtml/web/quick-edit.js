jQuery(document).ready(function($) {
    
    // Populate quick edit fields with existing data
    $(document).on('click', '.editinline', function() {
        var postId = $(this).closest('tr').find('.check-column input').val();
        var ymmData = $('.ymm-data[data-product-id="' + postId + '"]').text();
        
        setTimeout(function() {
            $('.ymm-data-field').val(ymmData);
        }, 100);
    });
});
