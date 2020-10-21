$(document).ready(() => {
    const $statusSelector = $('.filterService select[name="statut"]');

    initDateTimePicker();
    Select2.init($statusSelector, 'Statuts');
    Select2.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    Select2.user($('.filterService select[name="requesters"]'), "Demandeurs");
    Select2.user($('.filterService select[name="operators"]'), "Opérateurs");

    // sinon, filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_TRANSFER_ORDER);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');
});

let pathTransferOrder = Routing.generate('transfer_orders_api', true);
let transferOrderTableConfig = {
    processing: true,
    serverSide: true,
    order: [[1, 'desc']],
    ajax: {
        "url": pathTransferOrder,
        "type": "POST",
        'data': {
            'filterStatus': $('#filterStatus').val(),
            'filterReception': $('#receptionFilter').val()
        },
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        {"data": 'actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'number', 'name': 'Numéro', 'title': 'Numéro'},
        {"data": 'status', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'requester', 'name': 'Demandeur', 'title': 'Demandeur'},
        {"data": 'operator', 'name': 'Opérateur', 'title': 'Opérateur'},
        {"data": 'destination', 'name': 'Destination', 'title': 'Destination'},
        {"data": 'creationDate', 'name': 'Création', 'title': 'Date de création'},
        {"data": 'validationDate', 'name': 'Validation', 'title': 'Date de validation'},
    ]
};
let table = initDataTable('tableTransferOrders', transferOrderTableConfig);

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = table.column('Création:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

        if (
            (dateMin === "" && dateMax === "")
            ||
            (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);
