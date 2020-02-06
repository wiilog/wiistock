$(function() {
    initDateTimePicker();
    initSearchDate(tableMission);
    $('.select2').select2();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_SHOW_MISSION);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
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
    order: [[3, 'desc']],
    ajax:{
        "url": pathMission,
        "type": "POST",
    },
    'drawCallback': function() {
        overrideSearch($('#tableMissionInv_filter input'), tableMission);
    },
    'rowCallback': function(row, data) {
        if (data.EmptyLocation) alertErrorMsg('Il manque un ou plusieurs emplacements : ils n\'apparaîtront pas sur le nomade.');
    },
    columns:[
        { "data": 'Ref', 'title' : 'Reférence' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'Location', 'title' : 'Emplacement', 'name': 'location' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Anomaly', 'title' : 'Anomalie', 'name' : 'anomaly'  }
    ],
});

let modalAddToMission = $("#modalAddToMission");
let submitAddToMission = $("#submitAddToMission");
let urlAddToMission = Routing.generate('add_to_mission', true);
InitialiserModal(modalAddToMission, submitAddToMission, urlAddToMission, tableMission, null);
