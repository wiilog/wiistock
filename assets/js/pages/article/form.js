import '@styles/details-page.scss';
import {GET} from "@app/ajax";

$(function () {
    getTrackingMovements();

    /* A SUPPRIMER QUAND LA MODIFICATION SERA FAITE */
    let $modalEditArticle = $("#modalEditArticle");
    let $submitEditArticle = $("#submitEditArticle");
    let urlEditArticle = Routing.generate('article_edit', true);
    InitModal($modalEditArticle, $submitEditArticle, urlEditArticle, {
        success: () => window.location.reload()
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
