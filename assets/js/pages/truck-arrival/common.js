import AJAX, {POST} from "@app/ajax";
import Modal from "@app/modal";
import Select2 from "../../select2";
import {warning} from "@symfony/webpack-encore/lib/logger";

global.deleteTruckArrival = deleteTruckArrival;

export function initTrackingNumberSelect($trackingNumberSelect, $warningMessage, minTrackingNumberLength, maxTrackingNumberLength) {
    console.log($warningMessage)
    $trackingNumberSelect.off('change.lengthCheck').on('change.lengthCheck', function () {
        Select2.initSelectMultipleWarning(
            $trackingNumberSelect,
            $warningMessage,
            async ($option) => {
                let value = $option.val();
                return !((Boolean(minTrackingNumberLength) && value.length < minTrackingNumberLength) || (Boolean(maxTrackingNumberLength) && value.length > maxTrackingNumberLength));
            },
            {
                onWarning: () => {
                    setTrackingNumberWarningMessage($warningMessage, minTrackingNumberLength, maxTrackingNumberLength)
                }
            });
    });
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
