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
    toggleRequiredChampsLibres($select, 'create');
    typeChoice($select, '-new', $('#typeContentNew'))

    const type = parseInt($select.val());
    let $modalNewDispatch = $("#modalNewDispatch");
    const $selectStatus = $modalNewDispatch.find('select[name="statut"]');

    $selectStatus.removeAttr('disabled');
    $selectStatus.find('option[data-type-id="' + type + '"]').removeClass('d-none');
    $selectStatus.find('option[data-type-id!="' + type + '"]').addClass('d-none');
    $selectStatus.val(null).trigger('change');

    if($selectStatus.find('option:not(.d-none)').length === 0) {
        $selectStatus.siblings('.error-empty-status').removeClass('d-none');
        $selectStatus.addClass('d-none');
    } else {
        $selectStatus.siblings('.error-empty-status').addClass('d-none');
        $selectStatus.removeClass('d-none');

        const dispatchDefaultStatus = JSON.parse($selectStatus.siblings('input[name="dispatchDefaultStatus"]').val() || '{}');
        if (dispatchDefaultStatus[type]) {
            $selectStatus.val(dispatchDefaultStatus[type]);
        }
    }
}
