import {GET} from "../../ajax";

$(function() {
    const logisticUnitId = $(`[name=logisticUnitId]`).val();
    getTrackingHistory(logisticUnitId);
});

function getTrackingHistory(logisticUnitId) {
    return AJAX.route(GET, `pack_tracking_history_api`, {id: logisticUnitId})
        .json()
        .then(({template}) => {
            const $trackingHistoryContainer = $(`.history-container`);
            $trackingHistoryContainer.html(template);
        });
}
