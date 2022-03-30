import {initializeFilters} from "../common";

$(function() {
    initializeFilters(PAGE_TRANSPORT_ORDERS)

    let table = initDataTable('tableTransportOrders', {
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        ajax: {
            url: Routing.generate(`transport_order_api`),
            type: "POST"
        },
        domConfig: {
            removeInfo: true
        },
        //remove empty div with mb-2 that leaves a blank space
        drawCallback: () => $(`.row.mb-2 .col-auto.d-none`).parent().remove(),
        rowConfig: {
            needsRowClickAction: true
        },
        columns: [
            {data: 'content', name: 'content', orderable: false},
        ],
    });
});
