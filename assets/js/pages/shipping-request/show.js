$(function() {
    const shippingId = $('[name=shippingId]').val();
    AJAX.route('POST', 'get_transport_header_config', {
        id: shippingId
    })
        .json()
        .then(({detailsTransportConfig}) => {
            $('.transport-header').empty().append(detailsTransportConfig);
    });
})
