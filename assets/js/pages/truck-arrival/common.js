import AJAX, {POST} from "@app/ajax";
import Modal from "@app/modal";

global.deleteTruckArrival = deleteTruckArrival;

export function initTrackingNumberSelect($trackingNumberSelect, $warningMessage, minTrackingNumberLength, maxTrackingNumberLength) {
    $trackingNumberSelect.off('change.lengthCheck').on('change.lengthCheck', function () {
        let $options = $(this).find('option:selected')
        let isInvalidLength = false;

        // Wait for select2 to render the options
        setTimeout(function () {
            $options.each(function () {
                let $option = $(this);
                let value = $option.val();

                if ((Boolean(minTrackingNumberLength) && value.length < minTrackingNumberLength)
                    || (Boolean(maxTrackingNumberLength) && value.length > maxTrackingNumberLength)) {
                    $options.closest('label').find('.select2-container ul.select2-selection__rendered li.select2-selection__choice[title="' + value + '"]').addClass('warning');
                    isInvalidLength = true;
                    setTrackingNumberWarningMessage($warningMessage, minTrackingNumberLength, maxTrackingNumberLength);
                } else {
                    $option.removeClass('invalid');
                }
            });
            if (isInvalidLength) {
                $warningMessage.removeClass('d-none');
            } else {
                $warningMessage.addClass('d-none');
            }
        }, 10);
    })
}

export function setTrackingNumberWarningMessage($warningMessage, minTrackingNumberLength, maxTrackingNumberLength) {
    if (minTrackingNumberLength && maxTrackingNumberLength) {
        $warningMessage.find('.min-length').text(minTrackingNumberLength);
        $warningMessage.find('.max-length').text(maxTrackingNumberLength);
    } else if (maxTrackingNumberLength) {
        $warningMessage.text('Les numéros de tracking doivent faire maximum ' + maxTrackingNumberLength + ' caractères.');
    } else if (minTrackingNumberLength) {
        $warningMessage.text('Les numéros de tracking doivent faire minimum ' + minTrackingNumberLength + ' caractères.');
    }
}

export function deleteTruckArrival($deleteButton) {
    const truckArrivalId = $deleteButton.data('id');
    Modal.confirm({
        ajax: {
            method: POST,
            route: `truck_arrival_delete`,
            params: {
                truckArrival: truckArrivalId
            },
        },
        message: `Voulez-vous réellement supprimer cet arrivage camion ?`,
        title: `Supprimer l'arrivage camion`,
        validateButton: {
            color: `danger`,
            label: `Supprimer`,
        },
        cancelButton: {
            label: `Annuler`,
        },
    });
}
