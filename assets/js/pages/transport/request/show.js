import '../../../../scss/pages/transport-show.scss';

$(function () {
    const transportRequestId = $(`input[name=transportRequestId]`).val();

    getStatusHistory(transportRequestId);
});

function getStatusHistory(transportRequestId) {
    $.get(Routing.generate(`status_history_api`, {transportRequest: transportRequestId}, true))
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
