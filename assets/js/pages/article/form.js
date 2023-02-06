import '@styles/details-page.scss';
import {GET, POST} from "@app/ajax";

$(function () {
    const articleId = $(`input[name=article-id]`).val();

    if (articleId) {
        getTrackingMovements(articleId);
    } else {
        getFreeFieldsByType(null);
    }

    Form.create($(`.details-page-container`))
        .onSubmit((data) => {
            wrapLoadingOnActionButton($(`.save-buttons`).find(`button[type=submit]`), () => (
                AJAX.route(POST, articleId ? 'article_edit' : `article_new`)
                    .json(data)
                    .then(({articleId, success, barcode}) => {
                        if (success) {
                            window.location.href = Routing.generate('article_show_page', {id: articleId});
                        } else {
                            $(`input[name=barcode]`).val(barcode);
                        }
                    })
            ));
        });

    $(`select[name=refArticle]`).on(`change`, function () {
        const referenceId = $(this).val();

        getFreeFieldsByType(referenceId).then(() => {
            const $parent = $(this).closest(`.details-page-container`);
            const $supplierSelect = $parent.find(`select[name=fournisseur]`);

            $supplierSelect.prop(`disabled`, !referenceId);
            $supplierSelect.val(null).trigger(`change`);
        });
    });

    $(`select[name=fournisseur]`).on(`change`, function () {
        const supplierId = $(this).val();
        const $parent = $(this).closest(`.details-page-container`);
        const $supplierSelect = $parent.find(`select[name=articleFournisseur]`);

        $supplierSelect.prop(`disabled`, !supplierId);
        $supplierSelect.val(null).trigger(`change`);
    });
});

function getTrackingMovements(articleId) {
    return AJAX.route(GET, `get_article_tracking_movements`, {article: articleId})
        .json()
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.empty().html(template);
        });
}

function getFreeFieldsByType(referenceId) {
    const $parent = $(`.details-page-container`);
    const $freeFieldsContainer = $parent.find(`.free-fields-container`);
    const $loadingTemplate = $(`.loading-template`);
    $freeFieldsContainer.empty().html($loadingTemplate.html());

    return AJAX.route(GET, `get_free_fields_by_type`, {referenceId})
        .json()
        .then(({template, type}) => {
            $freeFieldsContainer.empty().html(template);
            $parent.find(`input[name=type]`).val(type);
        });
}
