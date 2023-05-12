$(function() {
    const shippingId = $('[name=shippingId]').val();

    getShippingRequestStatusHistory(shippingId);
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

function getShippingRequestStatusHistory(shippingRequest) {
    return $.get(Routing.generate(`shipping_request_status_history_api`, {shippingRequest}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.html(template);
        });
}
