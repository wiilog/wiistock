$(document).ready(() => {
    initDateTimePicker();
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    Select2Old.user($('.filterService select[name="requesters"]'), "Demandeurs");
    Select2Old.user($('.filterService select[name="operators"]'), "Opérateurs");

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
    order: [['number', 'desc']],
    ajax: {
        url: pathTransferOrder,
        type: "POST",
        data: {
            filterStatus: $('#filterStatus').val(),
            filterReception: $('#receptionFilter').val()
        },
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        {data: 'actions', title: '', className: 'noVis', orderable: false},
        {data: 'number', title: 'Numéro'},
        {data: 'status', title: 'Statut'},
        {data: 'requester', title: 'Demandeur'},
        {data: 'operator', title: 'Opérateur'},
        {data: 'origin', title: 'Origine'},
        {data: 'destination', title: 'Destination'},
        {data: 'creationDate', title: 'Date de création'},
        {data: 'transferDate', 'title': 'Date de traitement'},
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
