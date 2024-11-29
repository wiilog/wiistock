import Form from "@app/form";
import {POST} from "@app/ajax";

export function intCommentHistoryForm($modal, tableHistoLitige) {
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
