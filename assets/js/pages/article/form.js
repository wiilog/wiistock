import '@styles/details-page.scss';
import {GET} from "@app/ajax";

$(function () {
    getTrackingMovements();

    $(`.save`).click(function() {
        const $button = $(this);
        const $form = $(`.wii-form`);
        clearFormErrors($form);
        processSubmitAction($form, $button, $button.data(`submit`), {
            success: data => {
                window.location.href = Routing.generate('article_show_page', {id: data.data.id});
            },
        });
    });
});

function getTrackingMovements() {
    return AJAX.route(GET, `get_article_tracking_movements`, {article: $(`input[name=article-id]`).val()})
        .json()
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.empty().html(template);
        });
}
