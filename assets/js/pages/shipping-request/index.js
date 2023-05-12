import {GET} from "@app/ajax";

let tableShippings;

global.validateShippingRequest = validateShippingRequest;

$(function() {
    initTableShippings().then((table) => {
        tableShippings = table;
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
                type: "GET",
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

function validateShippingRequest(shipping_request_id){
    let id = shipping_request_id;
    AJAX.route(`GET`, `shipping_request_validation`, {id})
        .json()
        .then((res) => {
            if (res.success) {
                //location.reload()
            }
        });
}
