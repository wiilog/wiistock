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
        .on('change', '#modalNewArrivage [name="uploadAndScan"]', function () {
            scanDeliveryNoteFile($(this));
        });

    $(document)
        .on('change', '#modalNewArrivage [name="transporteur"]', function () {
            onCarrierChange($(this));
        });

    $(document)
        .on('change', '#modalNewArrivage [name="noTracking"]', function () {
            onTrackingChange($(this));
        });

    $(document)
        .on('change', '#modalNewArrivage [name="numeroCommandeList"]', function () {
            onNumeroCommandeListChange($(this));
        });

    $(document)
        .on('keyup click', '#modalNewArrivage .ql-editor', function () {
            onCommentEditInput($(this))
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
            .route(POST, `api_delivery_note_file`, {})
            .json(formData)
            .then(({success, data}) => {
                if (!success) {
                    return;
                }
                fillDeliveryNoteForm($modal, data);
            });
    });
}
function onCarrierChange($transporteur) {
    const carrier = $transporteur.val()
    const $button = $('[name="uploadAndScan"]').siblings("button")
    $button.prop('disabled', !carrier);
}
function onTrackingChange($noTracking) {
    const $noTrackingSelect2 = $noTracking
        .siblings('.select2-container')
        .find('.select2-selection');
    const hasValue = $noTracking.val() && $noTracking.val().length > 0;
    if (!hasValue) {
        $noTrackingSelect2.removeClass('ai-highlight');
    }
}
function onNumeroCommandeListChange($orderNumber) {
    const $orderNumberSelect2 = $orderNumber
        .siblings('.select2-container')
        .find('.select2-selection');
    const hasValue = $orderNumber.val() && $orderNumber.val().length > 0;
    if (!hasValue) {
        $orderNumberSelect2.removeClass('ai-highlight');
    }
}
function onCommentEditInput($editor) {
    const text = $editor.text().trim();
    if (!text) {
        $editor.removeClass('ai-highlight');
    }
}
function fillDeliveryNoteForm($modal, data) {
    const $comment = $modal.find('.ql-editor');
    const $selectNoTracking = $modal.find('[name="noTracking"]');
    const $noTrackingSelect2 = $selectNoTracking
        .siblings('.select2-container')
        .find('.select2-selection');

    $selectNoTracking.empty();
    $noTrackingSelect2.removeClass('ai-highlight');

    if (data.truck_arrival_lines) {
        const trackingNumbers = data.truck_arrival_lines;
        if (trackingNumbers.length) {
            $noTrackingSelect2.addClass('ai-highlight');
            trackingNumbers.forEach(trackingNumber => {
                $selectNoTracking.append(
                    new Option(trackingNumber.text, trackingNumber.id, true, true)
                );
                $selectNoTracking.trigger('select2:select.new-arrival', trackingNumber);
            });
        }
    }

    if (data.order_number) {
        const orderNumbers = Array.isArray(data.order_number)
            ? data.order_number
            : [data.order_number];

        const $selectOrderNumber = $modal.find('[name="numeroCommandeList"]');
        const $orderNumberSelect2 = $selectOrderNumber
            .siblings('.select2-container')
            .find('.select2-selection');

        $selectOrderNumber.empty();
        $orderNumberSelect2.removeClass('ai-highlight');

        if (orderNumbers.length) {
            $orderNumberSelect2.addClass('ai-highlight');
            orderNumbers.forEach(orderNumber => {
                $selectOrderNumber.append(
                    new Option(orderNumber, orderNumber, true, true)
                );
                $selectOrderNumber.trigger('change');
            });
        }
    }

    const fieldsToExclude = ["tracking_number", "truck_arrival_lines", "order_number"];
    const commentContent = Object
        .keys(data)
        .filter(key => !fieldsToExclude.includes(key))
        .map(key => {
            const value = data[key] || "";
            const label = KEY_TRANSLATIONS[key] || key;
            return `<p><strong>${label}</strong>: ${value.toString()}</p>`;
        })
        .join("");

    $comment.html(commentContent);

    if (commentContent.length > 0) {
        $comment.addClass('ai-highlight');
    } else {
        $comment.removeClass('ai-highlight');
    }
}
