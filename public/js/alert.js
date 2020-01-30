let pathAlerte = Routing.generate("alerte_ref_api", true);
let tableAlerte = $('#tableAlerte_id').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[4, "desc"]],
    ajax: {
        "url": pathAlerte,
        "type": "POST",
    },
    'drawCallback': function() {
        overrideSearch($('#tableAlerte_id_filter input'), tableAlerte);
    },
    columns: [
        { "data": 'Label', 'title': 'Libellé' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'QuantiteStock', 'title': 'Quantité en stock' },
        { "data": 'typeQuantite', 'title': 'Type quantité' },
        { "data": 'Date d\'alerte', 'title': 'Date d\'alerte' },
        { "data": "SeuilAlerte", 'title': "Seuil d'alerte" },
        { "data": 'SeuilSecurite', 'title': 'Seuil de sécurité' },
        { "data": 'Actions', 'name': 'Actions', 'title': 'Alerte'},
    ],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 4
        },
        { "orderable": false, "targets": 7 },
    ],
});

$(function() {
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ALERTE);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        extendsDateSort('customDate')
    });
});
