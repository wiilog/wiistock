$(function () {
    initDateTimePicker();
    initSearchDate(tableMissions);
    $('.select2').select2();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_MISSIONS);
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else if (element.field == 'anomaly') {
                $('#anomalyFilter').val(element.value);
            } else if (element.field == 'statut') {
                $('#' + element.field).val(element.value).select2();
            } else {
                $('#'+ element.field).val(element.value);
            }
        });
    }, 'json');
});

let pathMissions = Routing.generate('inv_missions_api', true);
let tableMissions = $('#tableMissionsInv').DataTable({
    serverSide: true,
    processing: true,
    searching: false,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[0, 'desc']],
    ajax:{
        "url": pathMissions,
        "type": "POST"
    },
    columns:[
        { "data": 'StartDate', 'title' : 'Date de début', 'name' : 'date' },
        { "data": 'EndDate', 'title' : 'Date de fin' },
        { "data": 'Rate', 'title' : 'Taux d\'avancement' },
        { "data": 'Actions', 'title' : 'Actions' }
    ],
    columnDefs: [
        {'orderable': false, 'targets': [2, 3]}
    ],
});

let modalNewMission = $("#modalNewMission");
let submitNewMission = $("#submitNewMission");
let urlNewMission = Routing.generate('mission_new', true);
InitialiserModal(modalNewMission, submitNewMission, urlNewMission, tableMissions, displayErrorMision, false);

let modalDeleteMission = $("#modalDeleteMission");
let submitDeleteMission = $("#submitDeleteMission");
let urlDeleteMission = Routing.generate('mission_delete', true)
InitialiserModal(modalDeleteMission, submitDeleteMission, urlDeleteMission, tableMissions);

function displayErrorMision(data) {
    let modal = $("#modalNewMission");
    let msg = null;
    if (data === false) {
        msg = 'La date de début doit être antérieure à celle de fin.';
        displayError(modal, msg, data);
    } else {
        modal.find('.close').click();
        msg = 'La mission d\'inventaire a bien été créée.';
        alertSuccessMsg(msg);
    }
}

$('#submitSearchMission').on('click', function() {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_INV_MISSIONS,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        anomaly: $('#anomalyFilter').val(),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableMissions);
});