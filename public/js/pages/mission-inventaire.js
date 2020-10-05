$(function () {
    initDateTimePicker();
    initSearchDate(tableMissions);
    $('.select2').select2();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_MISSIONS);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});

let pathMissions = Routing.generate('inv_missions_api', true);
let tableMisionsConfig = {
    serverSide: true,
    processing: true,
    searching: false,
    order: [[1, 'desc']],
    ajax:{
        "url": pathMissions,
        "type": "POST"
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    columns:[
        { "data": 'Actions', 'title' : '', className: 'noVis' },
        { "data": 'StartDate', 'title' : 'Date de début', 'name' : 'date' },
        { "data": 'EndDate', 'title' : 'Date de fin' },
        { "data": 'Rate', 'title' : 'Taux d\'avancement' },
    ],
    columnDefs: [
        {'orderable': false, 'targets': [0, 3]}
    ],
};
let tableMissions = initDataTable('tableMissionsInv', tableMisionsConfig);

let modalNewMission = $("#modalNewMission");
let submitNewMission = $("#submitNewMission");
let urlNewMission = Routing.generate('mission_new', true);
InitModal(modalNewMission, submitNewMission, urlNewMission, {tables: [tableMissions]});

let modalDeleteMission = $("#modalDeleteMission");
let submitDeleteMission = $("#submitDeleteMission");
let urlDeleteMission = Routing.generate('mission_delete', true)
InitModal(modalDeleteMission, submitDeleteMission, urlDeleteMission, {tables: [tableMissions]});
