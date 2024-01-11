global.openModalEditProductionRequest = openModalEditProductionRequest;
$(function () {
    const productionRequestId = $(`[name=productionRequestId]`).val();

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
    const $modalEditProductionRequest = $('#modalEditProductionRequest');
    Form
        .create($modalEditProductionRequest)
        .submitTo(AJAX.POST, 'production_request_edit');

    $modalEditProductionRequest.modal('show');
}
