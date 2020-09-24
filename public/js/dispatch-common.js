let editorNewDispatchAlreadyDone = false;

function initNewDispatchEditor(modal) {
    if (!editorNewDispatchAlreadyDone) {
        initEditorInModal(modal);
        editorNewDispatchAlreadyDone = true;
    }
    clearModal(modal);
    ajaxAutoUserInit($(modal).find('.ajax-autocomplete-user'));
    ajaxAutoCompleteTransporteurInit($(modal).find('.ajax-autocomplete-transporteur'));

    const $operatorSelect = $(modal).find('.ajax-autocomplete-user').first();
    const $loggedUserInput = $(modal).find('input[hidden][name="logged-user"]');
    let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
    $operatorSelect
        .val(null)
        .trigger('change')
        .append(option)
        .trigger('change');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement[name!=""]'));
}

function onDispatchTypeChange($select) {
    const $modal = $select.closest('.modal');
    OnTypeChange($select);

    const $selectedOption = $select.find('option:selected');
    const $pickLocationSelect = $modal.find('select[name="prise"]');
    const $dropLocationSelect = $modal.find('select[name="depose"]');

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
    $selectStatus.prop('disabled', $selectStatus.find('option:not(.d-none)').length > 0)
}
