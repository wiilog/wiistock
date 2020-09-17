let tableDispatches = null;
let editorNewDispatchAlreadyDone = false;

$(function() {
    initPage();

    initSelect2($('#statut'), 'Statuts');
    const filtersContainer = $('.filters-container');

    ajaxAutoUserInit(filtersContainer.find('.ajax-autocomplete-user[name=receivers]'), 'Destinataires');
    ajaxAutoUserInit(filtersContainer.find('.ajax-autocomplete-user[name=requesters]'), 'Demandeurs');
    initSelect2($('.filter-select2[name="multipleTypes"]'), 'Types');
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_DISPATCHES);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});

function initNewDispatchEditor(modal) {
    if (!editorNewDispatchAlreadyDone) {
        initEditorInModal(modal);
        editorNewDispatchAlreadyDone = true;
    }
    clearModal(modal);
    ajaxAutoUserInit($(modal).find('.ajax-autocomplete-user'));
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

function addInputColisClone(button)
{
    let $modal = button.closest('.modal-body');
    let $toClone = $modal.find('.inputColisClone').first();
    let $parent = $toClone.parent();
    $toClone.clone().appendTo($parent);
    $parent.children().last().find('.data-array').val('');
}

function onDispatchTypeChange($select) {
    toggleRequiredChampsLibres($select, 'create');

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

        $selectStatus.find('option');
    }
}

function initPage() {
    let tableDispatchesConfig = {
        serverSide: true,
        processing: true,
        order: [[1, "desc"]],
        ajax: {
            "url": Routing.generate('dispatch_api', true),
            "type": "POST",
        },
        rowConfig: {
            needsRowClickAction: true,
            needsColor: true,
            color: 'danger',
            dataToCheck: 'urgent'
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns: [
            { "data": 'actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false },
            { "data": 'number', 'name': 'number', 'title': 'Numéro demande' },
            { "data": 'creationDate', 'name': 'date', 'title': 'Date de création' },
            { "data": 'validationDate', 'name': 'validationDate', 'title': 'Date de validation' },
            { "data": 'type', 'name': 'type', 'title': 'Type' },
            { "data": 'requester', 'name': 'requester', 'title': 'Demandeur' },
            { "data": 'receiver', 'name': 'receiver', 'title': 'Destinataire' },
            { "data": 'locationFrom', 'name': 'locationFrom', 'title': 'acheminement.emplacement prise', translated: true },
            { "data": 'locationTo', 'name': 'locationTo', 'title': 'acheminement.emplacement dépose', translated: true },
            { "data": 'nbPacks', 'name': 'nbPacks', 'title': 'acheminement.Nb colis', orderable: false, translated: true },
            { "data": 'status', 'name': 'status', 'title': 'Statut' },
            { "data": 'urgent', 'name': 'urgent', 'title': 'Urgence' }
        ],
    };

    tableDispatches = initDataTable('tableDispatches', tableDispatchesConfig);

    let $modalNewDispatch = $("#modalNewDispatch");
    let $submitNewDispatch = $("#submitNewDispatch");
    let urlDispatchNew = Routing.generate('dispatch_new', true);
    InitModal($modalNewDispatch, $submitNewDispatch, urlDispatchNew, {tables: [tableDispatches]});

    let modalColumnVisible = $('#modalColumnVisibleDispatch');
    let submitColumnVisible = $('#submitColumnVisibleDispatch');
    let urlColumnVisible = Routing.generate('save_column_visible_for_dispatch', true);
    InitModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);
}
