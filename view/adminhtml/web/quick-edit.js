jQuery(document).ready(function($) {
    
    // Populate quick edit fields with existing data
    $(document).on('click', '.editinline', function() {
        var postId = $(this).closest('tr').find('.check-column input').val();
        var ymmData = $('.ymm-data[data-product-id="' + postId + '"]').text();
        
        setTimeout(function() {
            $('.ymm-data-field').val(ymmData);
        }, 100);
    });
    
    // Handle bulk edit action selection
    $(document).on('change', '.ymm-bulk-action', function() {
        var action = $(this).val();
        var $dataGroup = $('.ymm-bulk-data-group');
        
        if (action === 'replace' || action === 'add') {
            $dataGroup.show();
        } else {
            $dataGroup.hide();
        }
    });
    
    // Initialize bulk edit visibility
    $('.ymm-bulk-action').trigger('change');
});
