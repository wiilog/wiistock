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
        { "data": 'StartDate', 'title' : 'Date de début', 'name' : 'date' },
        { "data": 'EndDate', 'title' : 'Date de fin' },
        { "data": 'Rate', 'title' : 'Taux d\'avancement' },
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
        { "data": 'Article', 'title' : 'Reférence article ou article' },
        { "data": 'Date', 'title' : 'Date' },
        { "data": 'Anomaly', 'title' : 'Anomalie' }
    ],
});

let $submitSearchMission = $('#submitSearchMission');

$submitSearchMission.on('click', function () {

    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = tableMissions.column('date:name').index();
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
    tableMissions
        .draw();
});