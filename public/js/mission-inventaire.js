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
});

function generateCSVMission () {
    let params = {
        missionId: $('#missionId').val(),
    };
    let path = Routing.generate('get_mission_for_csv', true);

    $.post(path, JSON.stringify(params), function(response) {
        if (response) {
            let csv = "";
            $.each(response, function (index, value) {
                csv += value.join(';');
                csv += '\n';
            });
            mFile(csv);
        }
    }, 'json');
}

let mFile = function (csv) {
    let exportedFilenmae = 'export-mission' + '.csv';
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
};