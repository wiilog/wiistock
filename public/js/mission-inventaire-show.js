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
let pathApiArticle = Routing.generate('inv_entry_article_api', { id: mission}, true);
let tableMission = $('#tableMissionInvArticle').DataTable({
    processing: true,
    serverSide: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[3, 'desc']],
    scrollX: true,
    ajax:{
        "url": pathApiArticle,
        "type": "POST",
    },
    'drawCallback': function() {
        overrideSearch($('#tableMissionInvArticle_filter input'), tableMission);
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

let pathApiReferenceArticle = Routing.generate('inv_entry_reference_article_api', { id: mission}, true);
let tableMission2 = $('#tableMissionInvReferenceArticle').DataTable({
    processing: true,
    serverSide: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[3, 'desc']],
    scrollX: true,
    ajax:{
        "url": pathApiReferenceArticle,
        "type": "POST",
    },
    'drawCallback': function() {
        overrideSearch($('#tableMissionInvReferenceArticle_filter input'), tableMission2);
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
InitialiserModal(modalAddToMission, submitAddToMission, urlAddToMission, tableMission, displayErrorAddToMission);

function displayErrorAddToMission(data)
{
    if (!data) {
        alertErrorMsg("Cette référence est déjà présente dans une mission sur la même période. Vous ne pouvez pas l'ajouter.", true);
    }
}
