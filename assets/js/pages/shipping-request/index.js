import {GET, POST} from "@app/ajax";
import {initModalFormShippingRequest} from "@app/pages/shipping-request/form";

let tableShippings;

$(function() {
    initTableShippings().then((table) => {
        tableShippings = table;
    });
    initModalFormShippingRequest($('#modalNewShippingRequest'), 'shipping_request_new', (data) => {
        window.location.href = Routing.generate('shipping_request_show', {id: data.shippingRequestId});
    });
})


function initTableShippings() {
    let initialVisible = $(`#tableShippings`).data(`initial-visible`);
    if (!initialVisible) {
        return AJAX
            .route(GET, 'shipping_request_api_columns')
            .json()
            .then(columns => proceed(columns));
    } else {
        return new Promise((resolve) => {
            resolve(proceed(initialVisible));
        });
    }
    function proceed(columns) {
        let tableShippingsConfig = {
            pageLength: 10,
            processing: true,
            serverSide: true,
            paging: true,
            ajax: {
                url: Routing.generate('shipping_request_api', true),
                type: GET,
            },
            rowConfig: {
                needsRowClickAction: true,
            },
            columns: columns,
            hideColumnConfig: {
                columns,
                tableFilter: 'tableShippings'
            },
            drawConfig: {
                needsSearchOverride: true,
            },
            page: 'shippingRequest',
        };

        return initDataTable('tableShippings', tableShippingsConfig);
    }
}
