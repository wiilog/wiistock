import '@styles/pages/transport/show.scss';
import {getPacks, getStatusHistory, getTransportHistory, displayLastDelivererPosition, initMap} from "@app/pages/transport/common";

$(function () {
    const $modalCollectTimeSlot = $("#modalCollectTimeSlot");
    const transportId = Number($(`input[name=transportId]`).val());
    const transportType = $(`input[name=transportType]`).val();
    const contactPosition = JSON.parse($(`input[name=contactPosition]`).val());
    const roundId = Number($(`input[name=roundId]`).val());

    InitModal(
        $modalCollectTimeSlot,
        $modalCollectTimeSlot.find('.submit-button'),
        Routing.generate('validate_time_slot', true), {
        success: () => {
            window.location.reload();
        }}
    );

    getStatusHistory(transportId, transportType);
    getTransportHistory(transportId, transportType);
    getPacks(transportId, transportType);
    const map = initMap(contactPosition)

    if (roundId) {
        displayLastDelivererPosition(map, roundId);
    }
});

