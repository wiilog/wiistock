import {GET} from "@app/ajax";

let tableShippings;

global.validateShippingRequest = validateShippingRequest;
global.openScheduledShippingRequestModal = openScheduledShippingRequestModal;

$(function() {
    initTableShippings().then((table) => {
        tableShippings = table;
    });
    initScheduledShippingRequestForm();
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

function initScheduledShippingRequestForm(){
    let $modalScheduledShippingRequest = $('#modalScheduledShippingRequest');
    Form.create($modalScheduledShippingRequest).onSubmit((data, form) => {
        $modalScheduledShippingRequest.modal('hide');
        openPackingPack(data);
    });
}
function openPackingPack(dataShippingRequestForm){
    //todo WIIS-9591
}

function openScheduledShippingRequestModal($button){
    const id = $button.data('id')
    AJAX.route(`GET`, `check_expected_lines_data`, {id})
        .json()
        .then((res) => {
            if (res.success) {
                $('#modalScheduledShippingRequest').modal('show');
            }
        });
}
