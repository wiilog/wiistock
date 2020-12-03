let pathAlerte = Routing.generate("alerte_ref_api", true);
let tableAlerteConfig = {
    processing: true,
    serverSide: true,
    order: [[1, "desc"]],
    ajax: {
        "url": pathAlerte,
        "type": "POST",
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        { "data": "type", "title": "Type d'alerte" },
        { "data": "date", "title": "Date d'alerte" },
        { "data": "label", "title": "Libellé" },
        { "data": "reference", "title": "Référence" },
        { "data": "code", "title": "Code barre" },
        { "data": "quantity", "title": "Quantité disponible" },
        { "data": "quantityType", "title": "Type quantité" },
        { "data": "warningThreshold", "title": "Seuil d'alerte" },
        { "data": "securityThreshold", "title": "Seuil de sécurité" },
        { "data": "expiry", "title": "Date de péremption" },
        { "data": "managers", "title": "Gestionnaire(s)" },
    ],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 4
        }
    ],
};
let tableAlerte = initDataTable('tableAlerte', tableAlerteConfig);

$(function() {
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ALERTE);

    Select2.user($(".filter-select2.ajax-autocomplete-user"), "Gestionnaires");

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        extendsDateSort('customDate');
    });
});
