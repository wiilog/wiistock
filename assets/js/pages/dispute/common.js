import Form from "@app/form";
import {POST} from "@app/ajax";

export function initCommentHistoryForm($modal, tableHistoLitige) {
    const $commentForm = $modal.find('.comment-form')
    Form
        .create($commentForm , {
            submitButtonSelector: '.add-comment-on-dispute',
        })
        .submitTo(POST, 'dispute_add_comment', {
            tables: [tableHistoLitige],
            success: function () {
                console.log($commentForm.find('[name="comment"]'))
                $commentForm.find('[name="comment"]').val('');
            },
            routeParams: {
                'dispute': $('[name="disputeId"]').val(),
            }
        })
}

export function initTableArticleLitige() {
    let pathArticleLitige = Routing.generate('dispute_article_api', {dispute: $('[name="disputeId"]').val()}, true);
    let tableArticleLitigeConfig = {
        ajax: {
            "url": pathArticleLitige,
            "type": "POST"
        },
        columns: [
            {data: 'codeArticle', name: 'codeArticle', title: Translation.of('Qualité', 'Litiges', 'Code article')},
            {data: 'status', name: 'status', title: Translation.of('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Statut')},
            {data: 'libelle', name: 'libelle', title: Translation.of('Qualité', 'Litiges', 'Libellé')},
            {data: 'reference', name: 'reference', title: Translation.of('Qualité', 'Litiges', 'Référence article')},
            {data: 'quantity', name: 'quantity', title: Translation.of('Traçabilité', 'Général', 'Quantité')}
        ],
        domConfig: {
            needsPartialDomOverride: true,
        },
        "paging": false,

    };
    return initDataTable('tableArticleInLitige', tableArticleLitigeConfig);
}
