$('.select2').select2();

$(function() {
    initDateTimePicker();
    initSelect2($('.filter-select2[name="natures"]'), 'Natures');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_PACK);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
});

let pathPackAPI = Routing.generate('pack_api', true);
let tablePackConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    order: [[2, "desc"]],
    ajax: {
        "url": pathPackAPI,
        "type": "POST",
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns: [
        {"data": 'packNum', 'name': 'packNum', 'title': 'Numéro colis'},
        {"data": 'packNature', 'name': 'packNature', 'title': $('#packNature').val()},
        {"data": 'packLastDate', 'name': 'packLastDate', 'title': 'Date du dernier mouvement'},
        {"data": "packOrigin", 'name': 'packOrigin', 'title': 'Issu de', className: 'noVis'},
        {"data": "packLocation", 'name': 'packLocation', 'title': 'Emplacement'},
    ]
};
let tableMvt = initDataTable('packTable', tablePackConfig);

