$(function () {
    const transportId = Number($(`input[name=transportId]`).val());
    const transportType = $(`input[name=transportType]`).val();

    getStatusHistory(transportId, transportType);
    getTransportHistory(transportId, transportType);
    getPacks(transportId, transportType);
});

function getStatusHistory(transportId, transportType) {
    $.get(Routing.generate(`status_history_api`, {id:transportId,type: transportType}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.status-history-container`);
            $statusHistoryContainer.empty().append(template);
        });
}

function getTransportHistory(transportId, transportType) {
    $.get(Routing.generate(`transport_history_api`, {id: transportId, type: transportType}, true))
        .then(({template}) => {
            const $transportHistoryContainer = $(`.transport-history-container`);
            $transportHistoryContainer.empty().append(template);
        });
}

function getPacks(transportId, transportType) {
    $.get(Routing.generate(`transport_packs_api`, {id: transportId, type: transportType}, true))
        .then(({template}) => {
            const $packsContainer = $(`.packs-container`);
            $packsContainer.empty().append(template);
        });
}
