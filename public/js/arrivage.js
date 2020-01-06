let numberOfDataOpened = 0;
let clicked = false;
$('.select2').select2();

$(function() {
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ARRIVAGE);;

    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                let values = element.value.split(',');
                let $utilisateur = $('#utilisateur');
                values.forEach((value) => {
                    let valueArray = value.split(':');
                    let id = valueArray[0];
                    let username = valueArray[1];
                    let option = new Option(username, id, true, true);
                    $utilisateur.append(option).trigger('change');
                });
            } else if (element.field == 'providers') {
                let values = element.value.split(',');
                let $providers = $('#providers');
                values.forEach((value) => {
                    let valueArray = value.split(':');
                    let id = valueArray[0];
                    let name = valueArray[1];
                    let option = new Option(name, id, true, true);
                    $providers.append(option).trigger('change');
                });
            } else if (element.field == 'emergency') {
                if (element.value === '1') {
                    $('#urgence-filter').attr('checked', 'checked');
                }
            } else if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else {
                $('#'+element.field).val(element.value);
            }
        });

        initFilterDateToday();
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Destinataires');
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
});

function initFilterDateToday() {
    // par défaut filtre date du jour
    let today = new Date();
    let formattedToday = today.getFullYear() + '-' + (today.getMonth()+1)%12 + '-' + today.getUTCDate().toString();
    $('#dateMin').val(formattedToday);
    $('#dateMax').val(formattedToday);
}

let pathArrivage = Routing.generate('arrivage_api', true);
let tableArrivage = $('#tableArrivages').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[1, "desc"]],
    scrollX: true,
    ajax: {
        "url": pathArrivage,
        "type": "POST",
        'data': {
            'clicked' : function() {
                return clicked;
            }
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
    ]
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
        if (response.response.exists) {
            if (response.codeColis.length === 0) {
                alertErrorMsg("Il n'y a aucun colis à imprimer.");
            } else {
                for (const code of response.codeColis) {
                    codeColis.push(code.code)
                }
                printBarcodes(codeColis, response.response, ('Etiquettes.pdf'));
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
initModalWithAttachments(modalNewArrivage, submitNewArrivage, urlNewArrivage);

let editorNewArrivageAlreadyDone = false;
let quillNew;

function initNewArrivageEditor(modal) {
    if (!editorNewArrivageAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($(modal).find('.ajax-autocomplete-fournisseur'));
    ajaxAutoUserInit($(modal).find('.ajax-autocomplete-user'));
    ajaxAutoCompleteTransporteurInit($(modal).find('.ajax-autocomplete-transporteur'));
    ajaxAutoChauffeurInit($(modal).find('.ajax-autocomplete-chauffeur'));
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

function generateCSVArrivage () {
    loadSpinner($('#spinnerArrivage'));
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        moment(data['dateMin'], 'DD/MM/YYYY').format('YYYY-MM-DD');
        moment(data['dateMax'], 'DD/MM/YYYY').format('YYYY-MM-DD');
        let params = JSON.stringify(data);
        let path = Routing.generate('get_arrivages_for_csv', true);

        $.post(path, params, function(response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                aFile(csv);
                hideSpinner($('#spinnerArrivage'));
            }
        }, 'json');

    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
        hideSpinner($('#spinnerArrivage'))
    }
}

let aFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilenmae = 'export-arrivage-' + date + '.csv';
    let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilenmae);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) {
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilenmae);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}
