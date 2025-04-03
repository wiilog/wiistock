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

            $(document)
                .on('change', '[name="noTracking"]', function () {
                    const $noTrackingSelect2 = $(this)
                        .siblings('.select2-container')
                        .find('.select2-selection');
                    const hasValue = $(this).val() && $(this).val().length > 0;
                    if (!hasValue) {
                        $noTrackingSelect2.removeClass('ai-highlight');
                    }
                });

            $(document)
                .on('change', '[name="numeroCommandeList"]', function () {
                    const $orderNumberSelect2 = $(this)
                        .siblings('.select2-container')
                        .find('.select2-selection');
                    const hasValue = $(this).val() && $(this).val().length > 0;
                    if (!hasValue) {
                        $orderNumberSelect2.removeClass('ai-highlight');
                    }
                });

            $(document)
                .on('keyup click', '.ql-editor', function () {
                    const text = $(this).text().trim();
                    if (!text) {
                        $(this).removeClass('ai-highlight');
                    }
                });
        });
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
                if (!success) {
                    return
                }

                const $selectNoTracking = $modal.find('[name="noTracking"]');
                const $noTrackingSelect2 = $selectNoTracking.siblings('.select2-container').find('.select2-selection');

                $selectNoTracking.empty();

                if (data.truck_arrival_lines) {
                    let trackingNumbers = data.truck_arrival_lines;

                    if (trackingNumbers.length) {
                        $noTrackingSelect2.addClass('ai-highlight')
                        trackingNumbers.forEach(trackingNumber => {
                            $selectNoTracking.append(new Option(trackingNumber.text, trackingNumber.id, true, true));
                            $selectNoTracking.trigger('select2:select.new-arrival', trackingNumber);
                        });
                    } else {
                        $noTrackingSelect2.removeClass('ai-highlight');
                    }
                }

                if (data.order_number) {
                    let orderNumbers = Array.isArray(data.order_number)
                        ? data.order_number
                        : [data.order_number];

                    const $selectOrderNumber = $modal.find('[name="numeroCommandeList"]');
                    const $orderNumberSelect2 = $selectOrderNumber.siblings('.select2-container').find('.select2-selection');

                    if (orderNumbers.length) {
                        $orderNumberSelect2.addClass('ai-highlight');
                        orderNumbers.forEach(orderNumber => {
                            $selectOrderNumber.append(new Option(orderNumber, orderNumber, true, true));
                            $selectOrderNumber.trigger('change');
                        });

                    } else {
                        $orderNumberSelect2.removeClass('ai-highlight');
                    }
                }
                const fieldsToExclude = ["tracking_number", "truck_arrival_lines", "order_number"];
                const commentContent = Object
                    .keys(data)
                    .filter(key => !fieldsToExclude.includes(key))
                    .map((key) => {
                        const value = data[key] || "";
                        key = KEY_TRANSLATIONS[key] || "";
                        return `<p><strong>${key}</strong>: ${value.toString()}</p>`;
                    })
                    .join("")

                $comment.html(
                    commentContent
                );

                if (commentContent.length > 0) {
                    $comment.addClass('ai-highlight');
                } else {
                    $comment.removeClass('ai-highlight');
                }

            });
    });
}
