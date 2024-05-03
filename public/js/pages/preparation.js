$('.select2').select2();

$(function () {
    initDateTimePicker();
    Select2Old.demand($('.ajax-autocomplete-demande'));
    Select2Old.user('Opérateurs');

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
        let params = JSON.stringify(PAGE_PREPA);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }
});

let path = Routing.generate('preparation_api');
let tableConfig = {
    serverSide: true,
    processing: true,
    order: [['Date', 'desc']],
    ajax: {
        url: path,
        'data': {
            'filterDemand': $('#filterDemandId').val()
        },
        "type": "POST"
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns: [
        {data: 'Actions', title: '', name: 'Actions', className: 'noVis', orderable: false},
        {data: 'pairing', title: '', name: 'Actions', className: 'pairing-row', orderable: false},
        {data: 'Numéro', title: 'Numéro', name: 'Numéro'},
        {data: 'Statut', title: 'Statut', name: 'Statut'},
        {data: 'Date', title: 'Date de création', name: 'Date'},
        {data: 'Opérateur', title: 'Opérateur', name: 'Opérateur'},
        {data: 'Type', title: 'Type', name: 'Type'},
    ],
};
let table = initDataTable('table_id', tableConfig);
