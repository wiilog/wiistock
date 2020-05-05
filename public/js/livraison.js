$('.select2').select2();

$(function () {
    initDateTimePicker();
    initSelect2($('#statut'), 'Statut');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');
    ajaxAutoDemandesInit($('.ajax-autocomplete-demande'));

    // cas d'un filtre par demande de collecte
    let $filterDemand = $('.filters-container .filter-demand');
    $filterDemand.attr('name', 'demande');
    $filterDemand.attr('id', 'demande');
    let filterDemandId = $('#filterDemandId').val();
    let filterDemandValue = $('#filterDemandValue').val();

    if (filterDemandId && filterDemandValue) {
        let option = new Option(filterDemandValue, filterDemandId, true, true);
        $filterDemand.append(option).trigger('change');
    } else {
        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_ORDRE_LIVRAISON);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }
});

let pathLivraison = Routing.generate('livraison_api');
let tableLiraisonConfig = {
    serverSide: true,
    processing: true,
    order: [
        [3, "desc"]
    ],
    ajax: {
        'url': pathLivraison,
        'data': {
            'filterDemand': $('#filterDemandId').val()
        },
        "type": "POST"
    },
    rowConfig: {
        needsRowClickAction: true
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        {"data": 'Actions', 'title': '', 'name': 'Actions', className: 'noVis'},
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: 0
        }
    ],
};
let tableLivraison = initDataTable('tableLivraison_id', tableLiraisonConfig);

let pathArticle = Routing.generate('livraison_article_api', {'id': id});
let tableArticleConfig = {
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis'},
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
    order: [[1, "asc"]],
    columnDefs: [
        {orderable: false, targets: [0]}
    ]
};
let tableArticle = initDataTable('tableArticle_id', tableArticleConfig);

let modalDeleteLivraison = $('#modalDeleteLivraison');
let submitDeleteLivraison = $('#submitDeleteLivraison');
let urlDeleteLivraison = Routing.generate('livraison_delete', {'id': id}, true);
InitialiserModal(modalDeleteLivraison, submitDeleteLivraison, urlDeleteLivraison, tableLivraison);
