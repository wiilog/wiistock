import AJAX, {POST, GET} from "@app/ajax";
import {initModalFormShippingRequest} from "@app/pages/shipping-request/form";

global.validateShippingRequest = validateShippingRequest;
global.openScheduledShippingRequestModal = openScheduledShippingRequestModal;
global.shippedShippingRequest = shippedShippingRequest;

$(function() {
    const shippingId = $('[name=shippingId]').val();

    const $modalEdit = $('#modalEditShippingRequest');
    initModalFormShippingRequest($modalEdit, 'shipping_request_edit', () => {
        $modalEdit.modal('hide');
        window.location.reload();
    });

    initScheduledShippingRequestForm();
});

function refreshTransportHeader(shippingId){
    AJAX.route(POST, 'get_transport_header_config', {
        id: shippingId
    })
        .json()
        .then(({detailsTransportConfig}) => {
            $('.transport-header').empty().append(detailsTransportConfig);
        });
}
function validateShippingRequest(shipping_request_id){
    AJAX.route(GET, `shipping_request_validation`, {id:shipping_request_id})
        .json()
        .then((res) => {
            if (res.success) {
                location.reload()
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
    AJAX.route(GET, `check_expected_lines_data`, {id})
        .json()
        .then((res) => {
            if (res.success) {
                $('#modalScheduledShippingRequest').modal('show');
            }
        });
}

function shippedShippingRequest($button) {
    wrapLoadingOnActionButton($button, () => (
        AJAX.route(POST, `shipped_shipping_request`, {id: $button.data('id')})
            .json()
            .then((res) => {
                if (res.success) {
                    location.reload();
                }
            })
    ));
}
