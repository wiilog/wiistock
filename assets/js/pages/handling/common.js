export function getStatusHistory(id) {
    $.get(Routing.generate(`handling_status_history_api`, {id}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.html(template);
        });
}
