$(function () {
    initSearch(tableMission);
    initSearch(tableMissions);
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

let modalNewMission = $("#modalNewMission");
let submitNewMission = $("#submitNewMission");
let urlNewMission = Routing.generate('mission_new', true);
InitialiserModal(modalNewMission, submitNewMission, urlNewMission, tableMissions, null);

let modalDeleteMission = $("#modalDeleteMission");
let submitDeleteMission = $("#submitDeleteMission");
let urlDeleteMission = Routing.generate('mission_delete', true)
InitialiserModal(modalDeleteMission, submitDeleteMission, urlDeleteMission, tableMissions);

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
})

function initSearch(table) {
    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = table.column('date:name').index();

            if (typeof indexDate === "undefined") return true;

            let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

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
}