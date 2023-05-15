$(function() {
    const shippingId = $('[name=shippingId]').val();
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

function generateDeliverySlip($button, shippingId) {
    AJAX.route('POST', 'post_delivery_slip', {
        shippingRequest: shippingId
    });
    return shippingId;
}

