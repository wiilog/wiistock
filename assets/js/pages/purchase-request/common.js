export function onStatusChange($select){
    const $selectedOption = $select.find('option:selected');
    const $modal = $select.closest('.modal-body');

    $modal.find('[name=deliveryFee]').attr('required', Boolean($selectedOption.data('preventstatuschangewithoutdeliveryfees')));
}
