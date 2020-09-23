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
    const $selectedOption = $select.find('option:selected');

    toggleRequiredChampsLibres($select, 'create');
    typeChoice($select, '-new', $('#typeContentNew'))

    const type = parseInt($select.val());
    let $modalNewDispatch = $("#modalNewDispatch");
    const $selectStatus = $modalNewDispatch.find('select[name="statut"]');
    $selectStatus.find('option[data-type-id="' + type + '"]').removeClass('d-none');
    $selectStatus.find('option[data-type-id!="' + type + '"]').addClass('d-none');
    $selectStatus.val(null).trigger('change');

    const $pickLocationSelect = $modalNewDispatch.find('select[name="prise"]');
    const $dropLocationSelect = $modalNewDispatch.find('select[name="depose"]');

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

    if($selectStatus.find('option:not(.d-none)').length === 0) {
        $selectStatus.siblings('.error-empty-status').removeClass('d-none');
        $selectStatus.addClass('d-none');
    } else {
        $selectStatus.siblings('.error-empty-status').addClass('d-none');
        $selectStatus.removeClass('d-none');

        $selectStatus.find('option:not(.d-none)').prop('selected', true);
    }
}
