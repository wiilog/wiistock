export function getStatusHistory(transportId) {
    $.get(Routing.generate(`handling_status_history_api`, {id:transportId}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.status-history-container`);
            $statusHistoryContainer.html(template);
        });
}
