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
        { "data": 'Anomaly', 'title' : 'Anomalie', 'name' : 'anomaly' },
        { "data": 'Actions', 'title' : 'Actions' }
    ],
    "columnDefs": [
        {"visible" : false, "targets" : 2}
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
        { "data": 'Article', 'title' : 'Reférence' },
        { "data": 'Date', 'title' : 'Date de saisie' },
        { "data": 'Anomaly', 'title' : 'Anomalie' }
    ],
});

let $submitSearchMission = $('#submitSearchMission');

$submitSearchMission.on('click', function () {

    let anomaly = $('#anomalyFilter').val();

    tableMissions
        .columns('anomaly:name')
        .search(anomaly)
        .draw();

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