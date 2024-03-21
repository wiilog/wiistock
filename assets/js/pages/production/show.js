import AJAX, {DELETE, GET} from "@app/ajax";
import Form from "@app/form";
import Modal from "@app/modal";
import Camera from "@app/camera"
import {displayAttachmentRequired, openModalUpdateProductionRequestStatus} from '@app/pages/production/form'

global.deleteProductionRequest = deleteProductionRequest;
global.openModalEditProductionRequest = openModalEditProductionRequest;
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
            success: () => {
                window.location.reload();
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

function openModalEditProductionRequest(){
    const $modalEditProductionRequest = $('#modalEditProductionRequest');
    $modalEditProductionRequest.modal('show');
}

function deleteProductionRequest(id){
    Modal.confirm({
        ajax: {
            method: DELETE,
            route: `production_request_delete`,
            params: {productionRequest: id},
        },
        message: `Voulez-vous réellement supprimer cette demande de production ?`,
        title: `Supprimer la demande de production`,
        validateButton: {
            color: `danger`,
            label: `Supprimer`,
        },
    })
}
