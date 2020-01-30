let onFlyFormOpened = {};
let clicked = false;
$('.select2').select2();

$(function() {
    initDateTimePicker('#dateMin, #dateMax, .date-cl');
    initSelect2('#statut', 'Statut');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ARRIVAGE);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        initFilterDateToday();
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Destinataires');
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
    'drawCallback': function() {
        overrideSearch($('#tableArrivages_filter input'), tableArrivage);
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'Date', 'name': 'Date', 'title': 'Date'},
        {"data": "NumeroArrivage", 'name': 'NumeroArrivage', 'title': "N° d'arrivage"},
        {"data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur'},
        {"data": 'Chauffeur', 'name': 'Chauffeur', 'title': 'Chauffeur'},
        {"data": 'NoTracking', 'name': 'NoTracking', 'title': 'N° tracking transporteur'},
        {"data": 'NumeroBL', 'name': 'NumeroBL', 'title': 'N° commande / BL'},
        {"data": 'Fournisseur', 'name': 'Fournisseur', 'title': 'Fournisseur'},
        {"data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire'},
        {"data": 'Acheteurs', 'name': 'Acheteurs', 'title': 'Acheteurs'},
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

function getDataAndPrintLabels(codes) {
    let path = Routing.generate('arrivage_get_data_to_print', true);
    let param = codes;

    $.post(path, JSON.stringify(param), function (response) {
        let codeColis = [];
        let dropZones = [];
        if (response.response.exists) {
            if (response.codeColis.length === 0) {
                alertErrorMsg("Il n'y a aucun colis à imprimer.");
            } else {
                for (const code of response.codeColis) {
                    codeColis.push(code.code);
                    dropZones.push(response.dropzone);
                }
                if (!response.dropzone) dropZones = null;
                printBarcodes(codeColis, response.response, ('Etiquettes.pdf'), dropZones);
            }
        }
    });
}

function printBarcode(code) {
    let path = Routing.generate('get_print_data', true);

    $.post(path, function (response) {
        printBarcodes([code], response, ('Etiquette_' + code + '.pdf'));
    });
}

tableArrivage.on('responsive-resize', function (e, datatable) {
    datatable.columns.adjust().responsive.recalc();
});

let modalNewArrivage = $("#modalNewArrivage");
let submitNewArrivage = $("#submitNewArrivage");
let urlNewArrivage = Routing.generate('arrivage_new', true);
let redirectAfterArrival = $('#redirect').val();
initModalWithAttachments(modalNewArrivage, submitNewArrivage, urlNewArrivage, tableArrivage, createCallback, redirectAfterArrival === 1, redirectAfterArrival === 1);

function createCallback(response) {
    alertSuccessMsg('Votre arrivage a bien été créé.');
    if (response.printColis) {
        getDataAndPrintLabels(response.arrivageId);
    } if (response.printArrivage) {
        printBarcode(response.numeroArrivage);
    }
}

let editorNewArrivageAlreadyDone = false;
let quillNew;

function initNewArrivageEditor(modal) {
    onFlyFormOpened = {};
    onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
    onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
    onFlyFormToggle('chauffeurDisplay', 'addChauffeur', true);
    if (!editorNewArrivageAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($(modal).find('.ajax-autocomplete-fournisseur'));
    ajaxAutoUserInit($(modal).find('.ajax-autocomplete-user'));
    ajaxAutoCompleteTransporteurInit($(modal).find('.ajax-autocomplete-transporteur'));
    ajaxAutoChauffeurInit($(modal).find('.ajax-autocomplete-chauffeur'));
    $('.list-multiple').select2();
}

let $submitSearchArrivage = $('#submitSearchArrivage');
$submitSearchArrivage.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_ARRIVAGE,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
        users: $('#utilisateur').select2('data'),
        urgence: $('#urgence-filter').is(':checked'),
        providers: $('#providers').select2('data'),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    clicked = true;

    saveFilters(filters, tableArrivage);
});
