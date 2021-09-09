let pathAlerte = Routing.generate("alerte_ref_api", true);
let tableAlerteConfig = {
    processing: true,
    serverSide: true,
    order: [['type', "desc"]],
    ajax: {
        "url": pathAlerte,
        "type": "POST",
    },
    drawConfig: {
        needsSearchOverride: true,
        needsResize: true
    },
    rowConfig: {
        classField: 'colorClass'
    },
    columns: [
        { "data": 'actions', 'name': 'actions', 'title': '', 'orderable': false, className: 'noVis'},
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
    initDateTimePicker();
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ALERTE);

    Select2Old.user($(".filter-select2.ajax-autocomplete-user"), "Gestionnaires");
    Select2Old.init($(".filter-select2[name=multipleTypes]"), "Types");

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        extendsDateSort('customDate');
    });
});
