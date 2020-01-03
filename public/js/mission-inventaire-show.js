$(function() {
    initDateTimePicker();
    initSearchDate(tableMission);
    $('.select2').select2();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_SHOW_MISSION);
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else if (element.field == 'anomaly') {
                $('#anomalyFilter').val(element.value);
            } else {
                $('#'+ element.field).val(element.value);
            }
        });
    }, 'json');
});

let mission = $('#missionId').val();
let pathMission = Routing.generate('inv_entry_api', { id: mission}, true);
let tableMission = $('#tableMissionInv').DataTable({
    processing: true,
    serverSide: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[2, 'desc']],
    ajax:{
        "url": pathMission,
        "type": "POST",
    },
    'drawCallback': function() {
        overrideSearch($('#tableMissionInv_filter input'), tableMission);
    },
    columns:[
        { "data": 'Ref', 'title' : 'Reférence' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Anomaly', 'title' : 'Anomalie', 'name' : 'anomaly'  }
    ],
});

let modalAddToMission = $("#modalAddToMission");
let submitAddToMission = $("#submitAddToMission");
let urlAddToMission = Routing.generate('add_to_mission', true);
InitialiserModal(modalAddToMission, submitAddToMission, urlAddToMission, tableMission, null);

$('#submitSearchMissionRef').on('click', function() {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_INV_SHOW_MISSION,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        anomaly: $('#anomalyFilter').val(),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableMission);
});

function generateCSVMission () {
    loadSpinner($('#spinnerMission'));
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
            hideSpinner($('#spinnerMission'));
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