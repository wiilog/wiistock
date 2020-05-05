$(function () {
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_ENTRIES);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateur');

    initSearchDate(tableEntries);
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    $('.ajax-autocomplete-inv-entries').select2({
        ajax: {
            url: Routing.generate('get_ref_and_articles', {activeOnly: 0}, true),
            dataType: 'json',
            delay: 250,
        },
        placeholder: {
            id: 0,
            text: 'Référence',
        },
        language: {
            inputTooShort: function () {
                return 'Veuillez entrer au moins 3 caractères.';
            },
            searching: function () {
                return 'Recherche en cours...';
            },
            noResults: function () {
                return 'Aucun résultat.';
            }
        },
        minimumInputLength: 3,
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
        { "data": 'Ref', 'title' : 'Reférence', 'name': 'reference' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Location', 'title' : 'Emplacement', 'name': 'location' },
        { "data": 'Operator', 'title' : 'Opérateur', 'name': 'operator' },
        { "data": 'Quantity', 'title' : 'Quantité' }
    ],
};
let tableEntries = initDataTable('tableMissionEntries', tableEntriesConfig);
