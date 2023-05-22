global.validateShippingRequest = validateShippingRequest;
global.deleteShippingRequest = deleteShippingRequest;

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
function validateShippingRequest(shipping_request_id){
    AJAX.route(`GET`, `shipping_request_validation`, {id:shipping_request_id})
        .json()
        .then((res) => {
            if (res.success) {
                location.reload()
            }
        });
}

function deleteShippingRequest($event){
    const shipping_request_id = $event.data('id');

    AJAX.route(`DELETE`, `delete_shipping_request`, {id:shipping_request_id})
        .json()
        .then((res) => {
            if (!res.success && !res.msg) {
                showBSAlert('Une erreur est survenue lors de la suppression.', 'danger');
            }
            if(res.success){
                window.location.href = Routing.generate('shipping_request_index');
            }
        });
}
