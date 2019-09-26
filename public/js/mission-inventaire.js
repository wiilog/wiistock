$(function () {
    initSearchDate(tableMission);
    initSearchDate(tableMissions);
})

let pathMissions = Routing.generate('inv_missions_api', true);
let tableMissions = $('#tableMissionsInv').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathMissions,
        "type": "POST"
    },
    columns:[
        { "data": 'StartDate', 'title' : 'Date de début', 'name' : 'date' },
        { "data": 'EndDate', 'title' : 'Date de fin' },
        { "data": 'Rate', 'title' : 'Taux d\'avancement' },
        { "data": 'Anomaly', 'title' : 'Anomalie', 'name' : 'anomaly' },
        { "data": 'Actions', 'title' : 'Actions' }
    ],
    "columnDefs": [
        {"visible" : false, "targets" : 3}
    ],
});


let mission = $('#missionId').val();
let pathMission = Routing.generate('inv_entry_api', { id: mission}, true);
let tableMission = $('#tableMissionInv').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathMission,
        "type": "POST"
    },
    columns:[
        { "data": 'Ref', 'title' : 'Reférence' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Anomaly', 'title' : 'Anomalie', 'name' : 'anomaly'  }
    ],
});

let $submitSearchMission = $('#submitSearchMission');
$submitSearchMission.on('click', function () {
    let anomaly = $('#anomalyFilter').val();
    tableMissions
        .columns('anomaly:name')
        .search(anomaly)
        .draw();
});

let $submitSearchMissionRef = $('#submitSearchMissionRef');
$submitSearchMissionRef.on('click', function() {
    let anomaly = $('#anomalyFilter').val();
    tableMission
        .columns('anomaly:name')
        .search(anomaly === 'true' ? 'oui':'non')
        .draw();
});