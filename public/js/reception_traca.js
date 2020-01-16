$('.select2').select2();

$(function() {
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_RCPT_TRACA);
    $.post(path, params, function (data) {
        data.forEach(function (element) {
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
            }  else if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else if (element.field == 'statut') {
                $('#' + element.field).val(element.value).select2();
            } else {
                $('#' + element.field).val(element.value);
            }
        });
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Utilisateurs');
});

let $submitSearchMvt = $('#submitSearchRecep');
let pathRecep = Routing.generate('reception_traca_api', true);
let tableRecep = $('#tableRecepts').DataTable({
    serverSide: true,
    "processing": true,
    "order": [[1, "desc"]],
    'drawCallback': function() {
        overrideSearch($('#tableRecepts_filter input'), tableRecep);
    },
    buttons: [
        {
            extend: 'csv',
            exportOptions: {
                columns: [0, 1, 2, 3]
            }
        }
    ],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathRecep,
        "type": "POST"
    },
    "columnDefs": [
        {
            "type": "customDate",
            "targets": 1
        },
        {
            orderable: false,
            targets: 0
        }
    ],
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "Arrivage", 'name': 'Arrivage', 'title': "Arrivage"},
        {"data": 'Réception', 'name': 'Réception', 'title': 'Réception'},
        {"data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur'},
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
        let indexDate = tableRecep.column('date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

        if (
            (dateMin === "" && dateMax === "")
            ||
            (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);

let modalDeleteReception = $('#modalDeleteRecepTraca');
let submitDeleteReception = $('#submitDeleteRecepTraca');
let urlDeleteArrivage = Routing.generate('reception_traca_delete', true);
InitialiserModal(modalDeleteReception, submitDeleteReception, urlDeleteArrivage, tableRecep);

$submitSearchMvt.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_RCPT_TRACA,
        dateMin: $('#dateMin').val(),
        dateMax:  $('#dateMax').val(),
        arrivage_string:  $('#arrivage_string').val(),
        reception_string: $('#reception_string').val(),
        users: $('#utilisateur').select2('data'),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableRecep);
});

let customExport = function() {
    tableRecep.button('.buttons-csv').trigger();
};
