import AJAX, {POST} from "@app/ajax";

const KEY_TRANSLATIONS = {
    tracking_number: "Numéro de tracking",
    supplier: "Fournisseur",
    reference: "Référence article",
    quantity: "Quantité",
    order_number: "Numéro de commande",
    description: "Description",
    contact: "Destinataire",
};

$(function () {
    $(document)
        .on('change', `[name="uploadAndScan"]`, function () {
           scanDeliveryNoteFile($(this));
        });
    // TODO : WIIS-12340
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
});

function scanDeliveryNoteFile($input) {
    const $modal = $input.closest('.modal');
    const $comment = $modal.find('.ql-editor');
    const $loaderContainer = $modal.find('.modal-body, [data-dismiss="modal"], .modal-footer')


    wrapLoadingOnActionButton($loaderContainer, () => {
        const files = $input[0].files;
        const file = files[0];
        if (!file) {
            return;
        }
        const formData = new FormData();
        formData.append("file", file, file.name);
        return AJAX
            .route(POST, `api_delivery_note_file`, {}).json(formData).then(({success, data}) => {
                if(success) {
                    $comment.html(
                        Object
                            .keys(data)
                            .map((key) => {
                                const value = data[key] || "";
                                key = KEY_TRANSLATIONS[key] || "";
                                return `<p><strong>${key}</strong>: ${value.toString()}</p>`;
                            })
                            .join("")
                    );
            } else {
                // TODO : WIIS-12340
            }
        });
    });
}
