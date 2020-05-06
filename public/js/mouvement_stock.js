$('.select2').select2();

$(function() {
    initDateTimePicker();
    initSelect2($('#emplacement'), 'Emplacements');
    initSelect2($('#statut'), 'Types');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_STOCK);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacements", 3);
});

let pathMvt = Routing.generate('mouvement_stock_api', true);
let tableMvtStockConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    order: [[1, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    drawConfig: {
        needsSearchOverride: true,
        filterId: 'tableMvts_filter'
    },
    columns: [
        {"data": 'actions', 'name': 'Actions', 'title': ''},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": 'from', 'name': 'from', 'title': 'Issu de', className: 'noVis'},
        {"data": "refArticle", 'name': 'refArticle', 'title': 'Référence article'},
        {"data": "quantite", 'name': 'quantite', 'title': 'Quantité'},
        {"data": 'origine', 'name': 'origine', 'title': 'Origine'},
        {"data": 'destination', 'name': 'destination', 'title': 'Destination'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: [0, 2]
        }
    ]
};
let tableMvt = initDataTable('tableMvts', tableMvtStockConfig);

let modalDeleteArrivage = $('#modalDeleteMvtStock');
let submitDeleteArrivage = $('#submitDeleteMvtStock');
let urlDeleteArrivage = Routing.generate('mvt_stock_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);
