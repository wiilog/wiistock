let onFlyFormOpened = {};
let clicked = false;
$('.select2').select2();

$(function() {
    initDateTimePicker('#dateMin, #dateMax, .date-cl');
    initSelect2($('#statut'), 'Statut');
    initSelect2($('#carriers'), 'Transporteurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ARRIVAGE);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        initFilterDateToday();
    }, 'json');

    ajaxAutoUserInit($('.filters .ajax-autocomplete-user'), 'Destinataires');
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
    $('select[name="tableArrivages_length"]').on('change', function() {
        $.post(Routing.generate('update_user_page_length_for_arrivage'), JSON.stringify($(this).val()));
    });
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableArrivage = $('#tableArrivages').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    pageLength: $('#pageLengthForArrivage').val(),
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[1, "desc"]],
    scrollX: true,
    ajax: {
        "url": pathArrivage,
        "type": "POST",
        'data': {
            'clicked': () => clicked,
        }
    },
    drawCallback: function(resp) {
        overrideSearch($('#tableArrivages_filter input'), tableArrivage);
        hideColumns(tableArrivage, resp.json.columnsToHide);
    },
    columns: [
        {"data": 'Actions', 'name': 'actions', 'title': 'Actions'},
        {"data": 'Date', 'name': 'date', 'title': 'Date'},
        {"data": "NumeroArrivage", 'name': 'numeroArrivage', 'title': $('#noArrTranslation').val()},
        {"data": 'Transporteur', 'name': 'transporteur', 'title': 'Transporteur'},
        {"data": 'Chauffeur', 'name': 'chauffeur', 'title': 'Chauffeur'},
        {"data": 'NoTracking', 'name': 'noTracking', 'title': 'N° tracking transporteur'},
        {"data": 'NumeroBL', 'name': 'numeroBL', 'title': 'N° commande / BL'},
        {"data": 'Fournisseur', 'name': 'fournisseur', 'title': 'Fournisseur'},
        {"data": 'Destinataire', 'name': 'destinataire', 'title': 'Destinataire'},
        {"data": 'Acheteurs', 'name': 'acheteurs', 'title': $('#acheteursTranslation').val()},
        {"data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur'},
    ],
    columnDefs: [
        {
            targets: 0,
            className: 'noVis'
        },
        {
            orderable: false,
            targets: [0]
        }
    ],
    headerCallback: function(thead) {
        $(thead).find('th').eq(2).attr('title', "n° d'arrivage");
        $(thead).find('th').eq(2).attr('title', "acheteurs");
    },
    "rowCallback" : function(row, data) {
        if (data.urgent === true) $(row).addClass('table-danger');
    },
    dom: '<"row"<"col-4"B><"col-4"l><"col-4"f>>t<"bottom"ip>r',
    buttons: [
        {
            extend: 'colvis',
            columns: ':not(.noVis)',
            className: 'dt-btn'
        },
        // {
        //     extend: 'csv',
        //     className: 'dt-btn'
        // }
    ],
    "lengthMenu": [10, 25, 50, 100],
});

function listColis(elem) {
    let arrivageId = elem.data('id');
    let path = Routing.generate('arrivage_list_colis_api', true);
    let modal = $('#modalListColis');
    let params = { id: arrivageId };

    $.post(path, JSON.stringify(params), function(data) {
        modal.find('.modal-body').html(data);
    }, 'json');
}

tableArrivage.on('responsive-resize', function (e, datatable) {
    datatable.columns.adjust().responsive.recalc();
});

let $modalNewArrivage = $("#modalNewArrivage");
let submitNewArrivage = $("#submitNewArrivage");
let urlNewArrivage = Routing.generate('arrivage_new', true);
let redirectAfterArrival = $('#redirect').val();
initModalWithAttachments($modalNewArrivage, submitNewArrivage, urlNewArrivage, tableArrivage, (params) => arrivalCallback(true, params), redirectAfterArrival === 1);

let editorNewArrivageAlreadyDone = false;
let quillNew;

function initNewArrivageEditor(modal) {
    let $modal = $(modal);
    clearModal($modal);
    onFlyFormOpened = {};
    onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
    onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
    onFlyFormToggle('chauffeurDisplay', 'addChauffeur', true);
    if (!editorNewArrivageAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
    initSelect2($modal.find('.ajax-autocomplete-fournisseur'));
    initSelect2($modal.find('.ajax-autocomplete-transporteur'));
    initSelect2($modal.find('.ajax-autocomplete-chauffeur'));
    initSelect2($modal.find('.ajax-autocomplete-user'), '', 1);
    $modal.find('.list-multiple').select2();
}
