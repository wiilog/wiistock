$('.select2').select2();

let $submitSearchMvt = $('#submitSearchMvt');

$(function() {
    initDateTimePicker();
    initSelect2('#statut', 'Type');
    initSelect2('#emplacement', 'Emplacement');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_TRACA);
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
            } else if (element.field == 'emplacement') {
                $('#emplacement').val(element.value).select2();
            }  else if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else {
                $('#'+element.field).val(element.value);
            }
        });
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');
});

let pathMvt = Routing.generate('mvt_traca_api', true);
let tableMvt = $('#tableMvts').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[1, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    'drawCallback': function() {
        overrideSearch($('#tableMvts_filter input'), tableMvt);
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "colis", 'name': 'colis', 'title': "Colis"},
        {"data": 'location', 'name': 'location', 'title': 'Emplacement'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
    ],
    columnDefs: [
        {
            type: "customDate",
            targets: 1
        },
        {
            orderable: false,
            targets: 0
        }
    ],
});

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateStr = a.split(' ')[0];
        let hourStr = a.split(' ')[1];
        let dateSplitted = dateStr.split('/');
        let hourSplitted = hourStr.split(':');

        let date = new Date(dateSplitted[2], dateSplitted[1], dateSplitted[0], hourSplitted[0], hourSplitted[1], hourSplitted[2]);

        return Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), date.getHours(), date.getMinutes(), date.getSeconds());
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableMvt.column('date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

        if (
            (dateMin == "" && dateMax == "")
            ||
            (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);

let modalNewMvtTraca = $("#modalNewMvtTraca");
let submitNewMvtTraca = $("#submitNewMvtTraca");
let urlNewMvtTraca = Routing.generate('mvt_traca_new', true);
initModalWithAttachments(modalNewMvtTraca, submitNewMvtTraca, urlNewMvtTraca, tableMvt);

let modalEditMvtTraca = $("#modalEditMvtTraca");
let submitEditMvtTraca = $("#submitEditMvtTraca");
let urlEditMvtTraca = Routing.generate('mvt_traca_edit', true);
initModalWithAttachments(modalEditMvtTraca, submitEditMvtTraca, urlEditMvtTraca, tableMvt);

let modalDeleteArrivage = $('#modalDeleteMvtTraca');
let submitDeleteArrivage = $('#submitDeleteMvtTraca');
let urlDeleteArrivage = Routing.generate('mvt_traca_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);

$submitSearchMvt.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_MVT_TRACA,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        colis: $('#colis').val(),
        location: $('#emplacement').val(),
        statut: $('#statut').val(),
        users: $('#utilisateur').select2('data'),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableMvt);
});

function generateCSVMouvement () {
    loadSpinner($('#spinnerMouvementTraca'));
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
        let path = Routing.generate('get_mouvements_traca_for_csv', true);

        $.post(path, params, function(response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                mFile(csv);
                hideSpinner($('#spinnerMouvementTraca'));
            }
        }, 'json');

    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
        hideSpinner($('#spinnerMouvementTraca'));
    }
}

let mFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilenmae = 'export-mouvement-traca-' + date + '.csv';
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

let editorNewMvtTracaAlreadyDone = false;

function initNewMvtTracaEditor(modal) {
    if (!editorNewMvtTracaAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewMvtTracaAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
    ajaxAutoUserInit($('.ajax-autocomplete-user'));
};

let editorEditMvtTracaAlreadyDone = false;

function initEditMvtTracaEditor(modal) {
    if (!editorEditMvtTracaAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-edit');
        editorEditMvtTracaAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
    ajaxAutoUserInit($('.ajax-autocomplete-user-edit'));
};

function fillDateInNewModal() {
    const date = moment().format();
    $('#modalNewMvtTraca').find('.datetime').val(date.slice(0,16));
}