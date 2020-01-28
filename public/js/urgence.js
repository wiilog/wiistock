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
    order: [[1, "desc"]],
    columns:[
        { "data": 'actions', 'title': 'Actions' },
        { "data": 'start', 'name' : 'start','title' : 'Date de début' },
        { "data": 'end', 'name' : 'end', 'title' : 'Date de fin' },
        { "data": 'commande', 'name' : 'commande', 'title' : 'Numéro de commande' },
    ],
    drawCallback: function() {
        overrideSearch($('#tableUrgences_filter input'), tableUrgence);
    },
    columnDefs: [
        {
            "orderable" : false,
            "targets" : 0
        },
        {
            "type": "customDate",
            "targets": [1, 2]
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
    initDateTimePicker('#dateMin, #dateMax, #dateStart, #dateEnd');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_URGENCES);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});

function initDateTimePickerUrgence() {
    initDateTimePicker('#modalEditUrgence .datepicker', 'DD/MM/YYYY HH:mm');
    let $dateStartInput = $('#modalEditUrgence').find('.dateStart');
    let dateStart = $dateStartInput.data('date');

    let $dateEndInput = $('#modalEditUrgence').find('.dateFin');
    let dateEnd = $dateEndInput.data('date');

    $dateStartInput.val(moment(dateStart, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));
    $dateEndInput.val(moment(dateEnd, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));
}
