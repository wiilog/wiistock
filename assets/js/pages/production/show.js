import AJAX, {GET} from "@app/ajax";
import Form from "@app/form";
import Camera from "@app/camera"
import {displayAttachmentRequired, initDeleteProductionRequest, modalConfirmDeleteProductionRequest, openModalUpdateProductionRequestStatus} from '@app/pages/production/form'

global.displayAttachmentRequired = displayAttachmentRequired;

$(function () {
    const $modalEditProductionRequest = $('#modalEditProductionRequest');
    const productionRequestId = $(`[name=productionRequestId]`).val();
    Form
        .create($modalEditProductionRequest)
        .onOpen(() => {
            Camera.init(
                $modalEditProductionRequest.find(`.take-picture-modal-button`),
                $modalEditProductionRequest.find(`[name="files[]"]`)
            );
        })
        .submitTo(AJAX.POST, 'production_request_edit', {
            routeParams: {
                productionRequest: productionRequestId
            },
            success: (response) => {
                if(response.needModalConfirmationForGenerateDispatch) {
                    modalConfirmDeleteProductionRequest(productionRequestId)
                } else {
                    window.location.reload();
                }
            }
        });

    getStatusHistory(productionRequestId);
    getOperationHistory(productionRequestId);

    const $modalUpdateProductionRequestStatus = $(`#modalUpdateProductionRequestStatus`);

    $(document).on('click', '.open-modal-update-production-request-status', $modalUpdateProductionRequestStatus, (event) => {
        openModalUpdateProductionRequestStatus($(this), $modalUpdateProductionRequestStatus, productionRequestId, () => {
            window.location.reload();
        })
    });

    initDeleteProductionRequest();
});

function getStatusHistory(productionRequestId) {
    return AJAX.route(GET, `production_request_status_history_api`, {id: productionRequestId})
        .json()
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.html(template);
        });
}

export function getOperationHistory(productionRequestId) {
    return AJAX.route(GET, `production_request_operation_history_api`, {id: productionRequestId})
        .json()
        .then(({template}) => {
            const $operationHistoryContainer = $(`.operation-history-container`);
            $operationHistoryContainer.html(template);
        });
}
