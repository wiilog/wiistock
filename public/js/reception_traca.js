$('.select2').select2();

$(function() {
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_RCPT_TRACA);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Utilisateurs');
});

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

let customExport = function() {
    tableRecep.button('.buttons-csv').trigger();
};
