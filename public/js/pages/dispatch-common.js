function initNewDispatchEditor(modal) {
    clearModal(modal);
    const $modal = $(modal);
    onDispatchTypeChange($modal.find("[name=type]"));

    const $operatorSelect = $modal.find(`select[name=requester]`);
    const $loggedUserInput = $modal.find('input[hidden][name="logged-user"]');
    let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
    $operatorSelect
        .val(null)
        .trigger('change')
        .append(option)
        .trigger('change');
    initDatePickers();
}

function onDispatchTypeChange($select) {
    const $modal = $select.closest('.modal');
    onTypeChange($select);
    const $selectedOption = $select.find('option:selected');
    const $pickLocationSelect = $modal.find('select[name="prise"]');
    const $dropLocationSelect = $modal.find('select[name="depose"]');
    const $typeDispatchPickLocation = $modal.find(`input[name=typeDispatchPickLocation]`);
    const $typeDispatchDropLocation = $modal.find(`input[name=typeDispatchDropLocation]`);
    const dropLocationId = $selectedOption.data('drop-location-id');
    const dropLocationLabel = $selectedOption.data('drop-location-label');
    const pickLocationId = $selectedOption.data('pick-location-id');
    const pickLocationLabel = $selectedOption.data('pick-location-label');
    if (pickLocationId) {
        let option = new Option(pickLocationLabel, pickLocationId, true, true);
        $pickLocationSelect.append(option).trigger('change');
    } else {
        $pickLocationSelect.val(null).trigger('change');
    }
    if (dropLocationId) {
        let option = new Option(dropLocationLabel, dropLocationId, true, true);
        $dropLocationSelect.append(option).trigger('change');
    } else {
        $dropLocationSelect.val(null).trigger('change');
    }
    const $selectStatus = $modal.find('select[name="status"]');
    if(!$selectStatus.hasClass('d-none')) {
        $selectStatus.prop('disabled', true);
    }

    $typeDispatchPickLocation.val($select.val());
    $typeDispatchDropLocation.val($select.val());
}
