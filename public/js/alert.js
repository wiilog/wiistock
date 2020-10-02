let pathAlerte = Routing.generate("alerte_ref_api", true);
let tableAlerteConfig = {
    processing: true,
    serverSide: true,
    order: [[2, "asc"]],
    ajax: {
        "url": pathAlerte,
        "type": "POST",
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        { "data": 'Label', 'title': 'Libellé' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'Quantité stock', 'title': 'Quantité en stock' },
        { "data": 'typeQuantite', 'title': 'Type quantité' },
        { "data": 'Type', 'title': 'Type' },
        { "data": 'Date d\'alerte', 'title': 'Date d\'alerte' },
        { "data": "SeuilAlerte", 'title': "Seuil d'alerte" },
        { "data": 'SeuilSecurite', 'title': 'Seuil de sécurité' },
        { "data": 'Actions', 'name': 'Actions', 'title': 'Alerte', orderable: false},
    ],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 4
        }
    ],
};
let tableAlerte = initDataTable('tableAlerte_id', tableAlerteConfig);

$(function() {
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ALERTE);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        extendsDateSort('customDate')
    });
});
