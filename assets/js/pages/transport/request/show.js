import '@styles/pages/transport/show.scss';
import AJAX, {POST} from "@app/ajax";
import Flash from "@app/flash";
import {initializeForm, cancelRequest, initializePacking, deleteRequest,} from "@app/pages/transport/request/common";
import {getPacks, getStatusHistory, getTransportHistory} from "@app/pages/transport/common";


$(function () {
    const transportRequest = $(`input[name=transportId]`).val();
    const transportType = $(`input[name=transportType]`).val();

    getStatusHistory(transportRequest, transportType);
    getTransportHistory(transportRequest, transportType);
    getPacks(transportRequest, transportType);

    initializePacking(() => {
        getPacks(transportRequest, transportType);
        getStatusHistory(transportRequest, transportType);
        getTransportHistory(transportRequest, transportType);
    });

    $('.cancel-request-button').on('click', function(){
        cancelRequest($(this).data('request-id'));
    });

    $('.delete-request-button').on('click', function(){
        deleteRequest($(this).data('request-id'));
    });

    const $modals = $("#modalTransportDeliveryRequest, #modalTransportCollectRequest");
    $modals.each(function() {
        const $modal = $(this);
        const form = initializeForm($modal, true);
        form.onSubmit((data) => {
            submitTransportRequestEdit(form, data);
        });
    });
});

function submitTransportRequestEdit(form, data) {
    const $modal = form.element;
    const $submit = $modal.find('[type=submit]');

    const $transportRequest = $modal.find('[name=transportRequest]');
    const transportRequest = $transportRequest.val();

    wrapLoadingOnActionButton($submit, () => {
        return AJAX
            .route(POST, 'transport_request_edit', {transportRequest})
            .json(data)
            .then(({success, message}) => {
                if (success) {
                    $modal.modal('hide');
                }
                Flash.add(
                    success ? 'success' : 'danger',
                    message || `Une erreur s'est produite`
                );
                window.location.reload();
            });
    });
}
