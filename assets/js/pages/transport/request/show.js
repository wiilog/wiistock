import '@styles/pages/transport/show.scss';
import {initializeForm, cancelRequest} from "@app/pages/transport/request/common";
import AJAX, {POST} from "@app/ajax";
import Flash from "@app/flash";

$(function () {
    const transportRequestId = $(`input[name=transportRequestId]`).val();

    $('.cancel-request').on('click', function(){
        cancelRequest($(this).data('transport-request-id'));
    });

    getStatusHistory(transportRequestId);
    getTransportHistory(transportRequestId);

    const $modals = $("#modalTransportDeliveryRequest, #modalTransportCollectRequest");
    $modals.each(function() {
        const $modal = $(this);
        const form = initializeForm($modal, true);
        form.onSubmit((data) => {
            submitTransportRequestEdit(form, data);
        });
    });
});

function getStatusHistory(transportRequest) {
    $.get(Routing.generate(`transport_request_status_history_api`, {transportRequest}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.status-history-container`);
            $statusHistoryContainer.empty().append(template);
            $statusHistoryContainer.animate({
                scrollTop: $statusHistoryContainer.find(`.last-status-history`).offset().top
            }, 1000, () => {
                const $currentTitleLeft = $statusHistoryContainer.find(`.title-left.current`);
                $currentTitleLeft.css(`transform`, `scale(1.2)`);
                $currentTitleLeft.css(`transition`, `transform 330ms ease-in-out`);
                setTimeout(() => $currentTitleLeft.css(`transform`, `none`), 300);
            });
        });
}

function getTransportHistory(transportRequestId) {
    $.get(Routing.generate(`transport_history_api`, {transportRequest: transportRequestId}, true))
        .then(({template}) => {
            const $transportHistoryContainer = $(`.transport-history-container`);
            $transportHistoryContainer.empty().append(template);
        })
}

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
                table.ajax.reload();
            });
    });
}
