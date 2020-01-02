//initialisation editeur de texte une seule fois
let editorNewReceptionAlreadyDone = false;
let numberOfDataOpened = 0;
let tableReception;

$(function () {
    $('.select2').select2();
    $('.body-add-ref').css('display', 'none');

    const $submitSearchReception = $('#submitSearchReception');

    $submitSearchReception.on('click', function () {
        $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
        $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');
        let filters = {
            page: PAGE_RECEPTION,
            dateMin: $('#dateMin').val(),
            dateMax: $('#dateMax').val(),
            statut: $('#statut').val(),
            providers: $('#providers').select2('data'),
        };

        $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
        $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

        saveFilters(filters, tableReception);
    });

    ajaxAutoArticlesReceptionInit($('.select2-autocomplete-articles'));

    // RECEPTION
    let pathTableReception = Routing.generate('reception_api', true);
    tableReception = $('#tableReception_id').DataTable({
        serverSide: true,
        processing: true,
        order: [[1, "desc"]],
        "columnDefs": [
            {
                "type": "customDate",
                "targets": 1
            },
            {
                "orderable": false,
                "targets": 0
            }
        ],
        language: {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathTableReception,
            "type": "POST",
        },
        'drawCallback': function () {
            overrideSearch($('#tableReception_id_filter input'), tableReception);
        },
        columns: [
            {"data": 'Actions', 'title': 'Actions'},
            {"data": 'Date', 'title': 'Date de création'},
            {"data": 'DateFin', 'title': 'Date de fin de réception'},
            {"data": 'Numéro de commande', 'title': 'Numéro de commande'},
            {"data": 'Fournisseur', 'title': 'Fournisseur'},
            {"data": 'Référence', 'title': 'Référence'},
            {"data": 'Statut', 'title': 'Statut'},
            {"data": 'Commentaire', 'title': 'Commentaire'},
        ],
    });

    $.extend($.fn.dataTableExt.oSort, {
        "customDate-pre": function (a) {
            let dateParts = a.split('/'),
                year = parseInt(dateParts[2]) - 1900,
                month = parseInt(dateParts[1]),
                day = parseInt(dateParts[0]);
            return Date.UTC(year, month, day, 0, 0, 0);
        },
        "customDate-asc": function (a, b) {
            return ((a < b) ? -1 : ((a > b) ? 1 : 0));
        },
        "customDate-desc": function (a, b) {
            return ((a < b) ? 1 : ((a > b) ? -1 : 0));
        }
    });

    let modalReceptionNew = $("#modalNewReception");
    let SubmitNewReception = $("#submitReceptionButton");
    let urlReceptionIndex = Routing.generate('reception_new', true);
    InitialiserModal(modalReceptionNew, SubmitNewReception, urlReceptionIndex);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_RECEPTION);
    $.post(path, params, function (data) {
        data.forEach(function (element) {
            if (element.field == 'providers') {
                let values = element.value.split(',');
                let $providers = $('#providers');
                values.forEach((value) => {
                    let valueArray = value.split(':');
                    let id = valueArray[0];
                    let username = valueArray[1];
                    let option = new Option(username, id, true, true);
                    $providers.append(option).trigger('change');
                });
            }  else if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else {
                $('#' + element.field).val(element.value);
            }
        });
    }, 'json');

    ajaxAutoFournisseurInit($('.filters').find('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
});

//RECEPTION
function generateCSVReception() {
    loadSpinner($('#spinnerReception'));
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
        let path = Routing.generate('get_receptions_for_csv', true);

        $.post(path, params, function (response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                aFile(csv);
                hideSpinner($('#spinnerReception'));
            }
        }, 'json');

    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
        hideSpinner($('#spinnerReception'))
    }
}

function aFile(csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilenmae = 'export-reception-' + date + '.csv';
    let blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
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

function initNewReceptionEditor(modal) {
    if (!editorNewReceptionAlreadyDone) {
        initEditorInModal(modal);
        editorNewReceptionAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'));
    ajaxAutoCompleteTransporteurInit($(modal).find('.ajax-autocomplete-transporteur'));
}
