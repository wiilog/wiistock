$(function() {
    initDateTimePicker('#dateMin, #dateMax');
    initSelect2($('#statut'), 'Statut');
    ajaxAutoUserInit($('.filters .ajax-autocomplete-user'), 'Utilisateurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_IMPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');
});

let pathImport = Routing.generate('import_api');
let tableImport = $('#tableImport').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathImport,
        "type": "POST"
    },
    columns: [
        { "data": 'actions', 'title': 'Actions', orderable: false },
        { "data": 'startDate', 'title': 'Date début' },
        { "data": 'endDate', 'title': 'Date fin' },
        { "data": 'label', 'title': 'Nom import' },
        { "data": 'newEntries', 'title': 'Nvx enreg.' },
        { "data": 'updatedEntries', 'title': 'Mises à jour' },
        { "data": 'nbErrors', 'title': "Nombre d'erreurs" },
        { "data": 'status', 'title': 'Statut' },
        { "data": 'user', 'title': 'Utilisateur' },
    ],
    order: [[1, "desc"]],
    drawCallback: function() {
        overrideSearch($('#tableImport_filter input'), tableImport);
    }
});

let $modalNewImportFirst = $("#modalNewImportFirst");
let $submitNewFournisseurFirst = $("#submitNewImportFirst");
let urlNewImportFirst = Routing.generate('import_new', true);
initModalWithAttachments($modalNewImportFirst, $submitNewFournisseurFirst, urlNewImportFirst, tableImport, displaySecondModal, false);

let $modalNewImportSecond = $('#modalNewImportSecond');
let $submitNewFournisseurSecond = $("#submitNewImportSecond");
let urlNewImportSecond= Routing.generate('import_links', true);
InitialiserModal($modalNewImportSecond, $submitNewFournisseurSecond, urlNewImportSecond, null, displayConfirmationModal);

let $modalNewImportConfirm = $('#modalNewImportConfirm');
let $submitNewImportConfirm = $('#submitNewImportConfirm');
let urlNewImportConfirm = Routing.generate('import_confirm', true);
InitialiserModal($modalNewImportConfirm, $submitNewImportConfirm, urlNewImportConfirm, tableImport);

function displaySecondModal(data) {
    //TODO CG vérification 1 seul fichier + format csv
    if (data.success) {
        $modalNewImportSecond.find('tbody').html(data.html);
        $modalNewImportSecond.find('#submitNewImportSecond').val(data.importId);
        $('#openModalNewImportSecond').click();
        $modalNewImportFirst.find('.close').click();
    }
}

function displayConfirmationModal(data) {
    $modalNewImportConfirm.find('#submitNewImportConfirm').val(data.importId);
    $('#openModalNewImportConfirm').click();
    $modalNewImportSecond.find('.close').click();
}