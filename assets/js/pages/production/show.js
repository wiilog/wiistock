global.openModalEditProductionRequest = openModalEditProductionRequest;
const $modalEditProductionRequest = $('#modalEditProductionRequest');
$(function () {
    const productionRequestId = $(`[name=productionRequestId]`).val();

    Form
        .create($modalEditProductionRequest)
        .submitTo(AJAX.POST, 'production_request_edit', {
            success: () => {
                window.location.reload();
            }
        });

    getStatusHistory(productionRequestId);
    getOperationHistory(productionRequestId);
});

function getStatusHistory(productionRequestId) {
    return AJAX.route(AJAX.GET, `production_request_status_history_api`, {id: productionRequestId})
        .json()
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.html(template);
        });
}

export function getOperationHistory(productionRequestId) {
    return AJAX.route(AJAX.GET, `production_request_operation_history_api`, {id: productionRequestId})
        .json()
        .then(({template}) => {
            const $operationHistoryContainer = $(`.operation-history-container`);
            $operationHistoryContainer.html(template);
        });
}

function openModalEditProductionRequest(){
    $modalEditProductionRequest.modal('show');
}
