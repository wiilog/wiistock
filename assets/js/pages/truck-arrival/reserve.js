export function initReserveForm($modal) {
    $modal.on('change','.display-condition', function () {
        const checked = $(this).is(':checked');
        $(this).closest('.row').find('.displayed-on-checkbox').display(!checked);
        $(this).closest('tr').find('.enabled-on-checkbox button,.enabled-on-checkbox input').attr('disabled', !checked);
    })
    $modal.find('.display-condition').trigger('change');
}
