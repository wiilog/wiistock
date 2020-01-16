let pathUrgences = Routing.generate('urgence_api', true);
let tableUrgence = $('#tableUrgences').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathUrgences,
        "type": "POST"
    },
    columns:[
        { "data": 'start', 'name' : 'start','title' : 'Date de début' },
        { "data": 'end', 'name' : 'end', 'title' : 'Date de fin' },
        { "data": 'commande', 'name' : 'commande', 'title' : 'Numéro de commande' },
        { "data": 'actions', 'title': 'Actions' },
    ],
    drawCallback: function() {
        overrideSearch($('#tableUrgences_filter input'), tableUrgence);
    },
    columnDefs: [
        {
            "orderable" : false,
            "targets" : 3
        },
        {
            "type": "customDate",
            "targets": [0, 1]
        }
    ],
});

let $submitSearchUrgence = $('#submitSearchUrgence');

let modalNewUrgence = $('#modalNewUrgence');
let submitNewUrgence = $('#submitNewUrgence');
let urlNewUrgence = Routing.generate('urgence_new');
InitialiserModal(modalNewUrgence, submitNewUrgence, urlNewUrgence, tableUrgence);

let modalDeleteUrgence = $('#modalDeleteUrgence');
let submitDeleteUrgence = $('#submitDeleteUrgence');
let urlDeleteUrgence = Routing.generate('urgence_delete', true);
InitialiserModal(modalDeleteUrgence, submitDeleteUrgence, urlDeleteUrgence, tableUrgence);

let modalModifyUrgence = $('#modalEditUrgence');
let submitModifyUrgence = $('#submitEditUrgence');
let urlModifyUrgence = Routing.generate('urgence_edit', true);
InitialiserModal(modalModifyUrgence, submitModifyUrgence, urlModifyUrgence, tableUrgence);

$(function() {
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_URGENCES);
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else if (element.field == 'statut') {
                $('#' + element.field).val(element.value).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
    }, 'json');
});

$submitSearchUrgence.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_URGENCES,
        commande: $('#commande').val(),
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val()
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableUrgence);
});