$(function () {
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_ENTRIES);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    Select2.user('Opérateur');

    initSearchDate(tableEntries);
    Select2.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    Select2.articleReference($('.ajax-autocomplete-inv-entries'), {
        placeholder: `Référence article`,
        activeOnly: 1,
    });
});

let mission = $('#missionId').val();
let pathEntry = Routing.generate('entries_api', { id: mission}, true);
let tableEntriesConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    ajax:{
        "url": pathEntry,
        "type": "POST"
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns:[
        { "data": 'Ref', 'title' : 'Référence article', 'name': 'reference' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'barCode', 'title' : 'Code barre' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Location', 'title' : 'Emplacement', 'name': 'location' },
        { "data": 'Operator', 'title' : 'Opérateur', 'name': 'operator' },
        { "data": 'Quantity', 'title' : 'Quantité' }
    ],
};
let tableEntries = initDataTable('tableMissionEntries', tableEntriesConfig);
