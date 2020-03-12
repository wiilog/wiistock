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

let $modalNewImport = $("#modalNewImport");
let $submitNewImport = $("#submitNewImport");
let urlNewImportFirst = Routing.generate('import_new', true);
initModalWithAttachments($modalNewImport, $submitNewImport, urlNewImportFirst, tableImport, displaySecondModal, false);

function displayFirstModal() {
    let $modal = $('#modalNewImport');

    $.post(Routing.generate('get_first_modal_content'), function(resp) {
        $modal.find('.modal-body').html(resp);
        $modal.modal('show');
    })
}

function displaySecondModal(data) {
    //TODO CG vérification 1 seul fichier + format csv
    if (data.success) {
        $modalNewImport.find('.modal-body').html(data.html);
        $modalNewImport.find('[name="importId"]').val(data.importId);
        $submitNewImport.off();

        let urlNewImportSecond = Routing.generate('import_links', true);
        InitialiserModal($modalNewImport, $submitNewImport, urlNewImportSecond, null, displayConfirmationModal, false);
    }
}

function displayConfirmationModal(data) {
    if (data.success) {
        $modalNewImport.find('.modal-body').html(data.html);
        $submitNewImport.off();

        let urlNewImportConfirm = Routing.generate('import_confirm', true);
        InitialiserModal($modalNewImport, $submitNewImport, urlNewImportConfirm, tableImport);
    }
}