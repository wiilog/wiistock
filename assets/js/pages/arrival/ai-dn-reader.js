import AJAX, {POST} from "@app/ajax";
import button from "bootstrap/js/src/button";

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

    $(document)
        .on('change', '#modalNewArrivage [name="transporteur"]', function (event) {
            const carrier = $(event.target).val()
            const $button = $('[name="uploadAndScan"]').siblings("button")

            $button.prop('disabled', !carrier)

            console.log($button)

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
        formData.append("carrier", $modal.find('[name="transporteur"]').val());
        return AJAX
            .route(POST, `api_delivery_note_file`, {}).json(formData).then(({success, data}) => {
                if(!success) {
                    return
                }

                const $selectNoTracking = $modal.find('[name="noTracking"]');
                $selectNoTracking.empty();

                if (data.truck_arrival_lines) {
                    let trackingNumbers = data.truck_arrival_lines;

                    if (trackingNumbers.length) {
                        trackingNumbers.forEach(trackingNumber => {
                            $selectNoTracking.append(new Option(trackingNumber.text, trackingNumber.id, true, true));
                            $selectNoTracking.trigger('select2:select.new-arrival', trackingNumber);
                        });
                    }
                }

                if (data.order_number) {
                    let orderNumbers = Array.isArray(data.order_number)
                        ? data.order_number
                        : [data.order_number];

                    const $selectOrderNumber = $('[name="numeroCommandeList"]');
                    $selectOrderNumber.empty();

                    if (orderNumbers.length) {
                        orderNumbers.forEach(orderNumber => {
                            $selectOrderNumber.append(new Option(orderNumber, orderNumber, true, true));
                            $selectOrderNumber.trigger('change');
                        });
                    }
                }
                const fieldsToExclude = ["tracking_number","truck_arrival_lines", "order_number"];
                    $comment.html(
                        Object
                            .keys(data)
                            .filter(key => !fieldsToExclude.includes(key))
                            .map((key) => {
                                const value = data[key] || "";
                                key = KEY_TRANSLATIONS[key] || "";
                                return `<p><strong>${key}</strong>: ${value.toString()}</p>`;
                            })
                            .join("")
                    );
        });
    });
}
