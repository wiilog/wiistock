import Routing from '@app/fos-routing';
import {initDataTable, initSearchDate} from "@app/datatable";

$(() => {
    initDateTimePicker();
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    Select2Old.user($('.filterService select[name="requesters"]'), "Demandeurs");
    Select2Old.user($('.filterService select[name="operators"]'), "Opérateurs");

    getUserFiltersByPage(PAGE_TRANSFER_ORDER);

    let transferOrderTableConfig = {
        processing: true,
        serverSide: true,
        order: [['number', 'desc']],
        ajax: {
            url: Routing.generate('transfer_orders_api', true),
            type: "POST",
            data: {
                filterStatus: $('#filterStatus').val(),
                filterReception: $('#receptionFilter').val()
            },
        },
        rowConfig: {
            needsRowClickAction: true,
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

    initSearchDate(table, 'Création');
});

