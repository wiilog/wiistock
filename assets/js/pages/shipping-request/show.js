import {POST} from "@app/ajax";

$(function() {
    const shippingId = $('[name=shippingId]').val();

    const $modalEdit = $('#modalEditShippingRequest');
    Form
        .create($modalEdit)
        .submitTo(POST, 'shipping_request_edit', {success: (data) => {
                $modalEdit.modal('hide');
                window.location.reload();
        }});
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
