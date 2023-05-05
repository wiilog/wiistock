import {POST} from "@app/ajax";
import {initModalFormShippingRequest} from "@app/pages/shipping-request/form";

$(function() {
    const shippingId = $('[name=shippingId]').val();

    const $modalEdit = $('#modalEditShippingRequest');
    initModalFormShippingRequest($modalEdit, 'shipping_request_edit', () => {
        $modalEdit.modal('hide');
        window.location.reload();
    });
});

function refreshTransportHeader(shippingId){
    AJAX.route('POST', 'get_transport_header_config', {
        id: shippingId
    })
        .json()
        .then(({detailsTransportConfig}) => {
            $('.transport-header').empty().append(detailsTransportConfig);
        });
}
