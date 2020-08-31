let editorNewDispatchAlreadyDone = false;


$(function() {
    initPage();

    initSelect2($('#statut'), 'Statuts');
    initSelect2($('#utilisateur'), 'Demandeur');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');
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
    ajaxAutoUserInit($('.ajax-autocomplete-user'));
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

function availableStatusOnChange($select) {
    const type = parseInt($select.val());
    $('select[name="statut"] option[data-type-id="' + type + '"]').removeClass('d-none');
    $('select[name="statut"] option[data-type-id!="' + type + '"]').addClass('d-none');
    $('select[name="statut"] option:selected').prop("selected", false);
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
            { "data": 'locationFrom', 'name': 'locationFrom', 'title': $('#dispatchLocationFrom').val() },
            { "data": 'locationTo', 'name': 'locationTo', 'title': $('#dispatchLocationTo').val() },
            { "data": 'nbPacks', 'name': 'nbPacks', 'title': 'Nb Colis', orderable: false },
            { "data": 'status', 'name': 'status', 'title': 'Statut' },
            { "data": 'urgent', 'name': 'urgent', 'title': 'Urgence' }
        ],
    };
    let tableDispatches = initDataTable('tableDispatches', tableDispatchesConfig);

    let $modalNewDispatch = $("#modalNewDispatch");
    let $submitNewDispatch = $("#submitNewDispatch");
    let urlDispatchNew = Routing.generate('dispatch_new', true);
    initModalWithAttachments($modalNewDispatch, $submitNewDispatch, urlDispatchNew, tableDispatches);
}
