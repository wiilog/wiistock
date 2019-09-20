let pathMissions = Routing.generate('invMissions_api', true);
let tableMissions = $('#tableMissionsInv').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathMissions,
        "type": "POST"
    },
    columns:[
        { "data": 'StartDate', 'title' : 'Date de début' },
        { "data": 'EndDate', 'title' : 'Date de fin' },
        { "data": 'Actions', 'title' : 'Actions' }
    ],
});


let mission = $('#missionId').val();
let pathMission = Routing.generate('invEntry_api', { id: mission}, true);
let tableMission = $('#tableMissionInv').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathMission,
        "type": "POST"
    },
    columns:[
        { "data": 'RefArticle', 'title' : 'Reférence article' },
        { "data": 'Date', 'title' : 'Date' },
        { "data": 'Anomaly', 'title' : 'Anomalie' }
    ],
});