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

function displayFirstModal() {
    clearModal($modalNewImport);
    $submitNewImport.off();
    let urlNewImportFirst = Routing.generate('import_new', true);
    initModalWithAttachments($modalNewImport, $submitNewImport, urlNewImportFirst, tableImport, displaySecondModal, false);

    $.get(Routing.generate('get_first_modal_content'), function(resp) {
        $modalNewImport.find('.modal-body').html(resp);
        $modalNewImport.modal('show');
    })
}

function displaySecondModal(data) {
    if (data.success) {
        $modalNewImport.find('.modal-body').html(data.html);
        $modalNewImport.find('[name="importId"]').val(data.importId);
        $submitNewImport.off();

        let urlNewImportSecond = Routing.generate('import_links', true);
        InitialiserModal($modalNewImport, $submitNewImport, urlNewImportSecond, null, displayConfirmationModal, false);
    } else {
        $modalNewImport.find('.error-msg').html(data.msg);
    }
}

function displayConfirmationModal(data) {
    if (data.success) {
        $modalNewImport.find('.modal-body').html(data.html);
        $submitNewImport.off();

        let urlNewImportConfirm = Routing.generate('import_confirm', true);
        InitialiserModal($modalNewImport, $submitNewImport, urlNewImportConfirm, tableImport, launchImport);
    } else {
        $modalNewImport.find('.error-msg').html(data.msg);
    }
}

function launchImport(data) {
    alertSuccessMsg('Votre import a bien été lancé. Vous pouvez poursuivre votre navigation.');
    $.post(Routing.generate('import_launch'), {importId: data.importId});
}