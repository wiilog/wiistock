import '@styles/details-page.scss';
import {GET} from "@app/ajax";
let dataToDisplay;
let step = 10;
let noDataLeft = false;

$(function () {
    getTrackingMovements();

    $(`.history-container .load-more`).on(`click`, () => {
        getTrackingMovements(10);
    });

    $(`.history-container`).on(`scroll`, function() {
        if($(this).scrollTop() + $(this).innerHeight() >= $(this)[0].scrollHeight) {
            if(!noDataLeft) {
                dataToDisplay += step;
                wrapLoadingOnActionButton($(this).parents(`.content`), () => getTrackingMovements(dataToDisplay));
            }
        }
    });

    /* A SUPPRIMER QUAND LA MODIFICATION SERA FAITE */
    let $modalEditArticle = $("#modalEditArticle");
    let $submitEditArticle = $("#submitEditArticle");
    let urlEditArticle = Routing.generate('article_edit', true);
    InitModal($modalEditArticle, $submitEditArticle, urlEditArticle, {
        success: () => window.location.reload()
    });
});

function getTrackingMovements(start = 10) {
    return AJAX.route(GET, `get_article_tracking_movements`, {article: $(`input[name=article-id]`).val(), start})
        .json()
        .then(({template, filtered, total}) => {
            const $statusHistoryContainer = $(`.history-container`);
            dataToDisplay = start;
            $statusHistoryContainer.empty().html(template);
            if(filtered === total) {
                noDataLeft = true;
                $statusHistoryContainer.remove(`.no-data-left`);
                $statusHistoryContainer.append(`<div class="wii-subtitle text-center no-data-left my-5">Toutes les données ont été chargées.</div>`);
            }
        });
}
