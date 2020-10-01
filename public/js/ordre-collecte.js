$('.select2').select2();

let pathCollecte = Routing.generate('ordre_collecte_api');
let tableCollecteConfig = {
    serverSide: true,
    processing: true,
    order: [[3, 'desc']],
    ajax: {
        'url': pathCollecte,
        'data': {
            'filterDemand': $('#filterDemandId').val()
        },
        "type": "POST"
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns: [
        {"data": 'Actions', 'title': '', 'name': 'Actions', className: 'noVis', orderable: false},
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
    ],
};
let tableCollecte = initDataTable('tableCollecte', tableCollecteConfig);
$(function () {
    initDateTimePicker();
    initSelect2($('#statut'), 'Statuts');
    ajaxAutoDemandCollectInit($('.ajax-autocomplete-dem-collecte'));
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');

    // cas d'un filtre par demande de collecte
    let $filterDemand = $('.filters-container .filter-demand');
    $filterDemand.attr('name', 'demCollecte');
    $filterDemand.attr('id', 'demCollecte');
    let filterDemandId = $('#filterDemandId').val();
    let filterDemandValue = $('#filterDemandValue').val();

    if (filterDemandId && filterDemandValue) {
        let option = new Option(filterDemandValue, filterDemandId, true, true);
        $filterDemand.append(option).trigger('change');
    } else {

        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_ORDRE_COLLECTE);

        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }
});
