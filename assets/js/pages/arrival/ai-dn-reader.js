import AJAX, {POST} from "@app/ajax";

$(function () {
    $(document)
        .on('change', `[name="uploadAndScan"]`, function () {
           scanDeliveryNoteFile($(this));
        })
    //     .on('change', '.ai-field', function () {
    //         const $field = $(this);
    //         $field.parent().find('.score-low, .score-medium, .score-high').removeClass('score-low score-medium score-high');
    //         let aiScoreText = $(this).prev().hasClass('ai-score-text') ? $(this).prev() : $(this).prev().find('.ai-score-text');
    //         aiScoreText.html('');
    //     })
    //     .on('click', '.ql-editor', function () {
    //         $(this).parent().prev().removeClass('score-low score-medium score-high');
    //         $(this).parent().parent().find('.ai-score-text').html('');
    //     })
})

function scanDeliveryNoteFile($input) {
    const $modal = $input.closest('.modal');
    const $modalBody = $modal.find('.modal-body');
    const $comment = $modal.find('.ql-editor')
    $modalBody.pushLoader();
    let files = $input[0].files;
    let file = files[0];
    let formData = new FormData();
    const displayScannedDeliveryNote = parseInt($('#displayScannedDeliveryNote').val());
    formData.append("file", file, file.name);

    AJAX
        .route(POST, `api_delivery_note_file`, {}).json(formData).then(({success, data}) => {
            console.log(data)
            if(success) {
                let comment = "";
                $.each(data, function(key, value) {
                    const keyTranslation = {
                        tracking_number: "Numéro de tracking",
                        supplier: "Fournisseur",
                        reference: "Référence article",
                        quantity: "Quantité",
                        order_number: "Numéro de commande",
                        description: "Description",
                        contact : "Destinataire"

                    }
                    key = keyTranslation[key]
                    comment += `<p><strong>${key}</strong>: ${value.toString()}</p>`
                });

                $comment.html(comment)
            } else {

            }
        $modalBody.popLoader();
    })
}
