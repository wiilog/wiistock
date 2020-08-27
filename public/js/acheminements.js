let pathacheminements = Routing.generate('acheminements_api', true);
let tableAcheminementsConfig = {
    serverSide: true,
    processing: true,
    order: [[1, "desc"]],
    ajax: {
        "url": pathacheminements,
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
        { "data": 'date', 'name': 'date', 'title': 'Date demande' },
        { "data": 'type', 'name': 'type', 'title': 'Type' },
        { "data": 'requester', 'name': 'requester', 'title': 'Demandeur' },
        { "data": 'receiver', 'name': 'receiver', 'title': 'Destinataire' },
        { "data": 'locationFrom', 'name': 'locationFrom', 'title': $('#takenLocationAcheminement').val() },
        { "data": 'locationTo', 'name': 'locationTo', 'title': $('#dropOffLocationAcheminement').val() },
        { "data": 'nbPacks', 'name': 'nbPacks', 'title': 'Nb Colis', orderable: false },
        { "data": 'status', 'name': 'status', 'title': 'Statut' },
        { "data": 'urgent', 'name': 'urgent', 'title': 'Urgence' }
    ],
};
let tableAcheminements = initDataTable('tableAcheminement', tableAcheminementsConfig);

let modalNewAcheminements = $("#modalNewAcheminements");
let submitNewAcheminements = $("#submitNewAcheminements");
let urlNewAcheminements = Routing.generate('acheminements_new', true);
initModalWithAttachments(modalNewAcheminements, submitNewAcheminements, urlNewAcheminements, tableAcheminements);

$(function() {
    initSelect2($('#statut'), 'Statuts');
    initSelect2($('#utilisateur'), 'Demandeur');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');
    initSelect2($('.filter-select2[name="multipleTypes"]'), 'Types');
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ACHEMINEMENTS);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});

let editorNewAcheminementAlreadyDone = false;

function initNewAcheminementEditor(modal) {
    if (!editorNewAcheminementAlreadyDone) {
        initEditorInModal(modal);
        editorNewAcheminementAlreadyDone = true;
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
