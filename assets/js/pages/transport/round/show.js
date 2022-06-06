import '@styles/pages/transport/common.scss';
import '@styles/pages/transport/round-plan.scss'
import {Map} from '@app/map';
import {getStatusHistory, getTransportRoundTimeline} from "@app/pages/transport/common";

$(function () {
    const map = Map.create(`map`);

    const transportRound= $(`input[name=transportId]`).val();
    const transportType = $(`input[name=transportType]`).val();

    getStatusHistory(transportRound, transportType);
    getTransportRoundTimeline(transportRound);

    const calculationPoint = JSON.parse($(`input[name=calculationPoints]`).val());
    const transportPoints = JSON.parse($(`input[name=transportPoints]`).val());

    map.setLines(placePoints(map, calculationPoint, transportPoints), "#3353D7");
    map.fitBounds();

    const delivererPosition= $(`input[name=delivererPosition]`).val();
    if (delivererPosition) {
        let position = delivererPosition.split(',');
        map.setMarker({
            latitude : position[0],
            longitude : position[1],
            icon : "delivererLocation",
            popUp: "",
            name: "Deliverer",
        });
    }

    $('.print-round-button').on('click', function (){
        roundPDF($(this).data('round-id'));
    });
});


function placePoints(map, calculationPoint, transportPoints) {
    let coordinates = [];

    coordinates.push(placePoint(map, calculationPoint.startPoint, "blackLocation" ));
    coordinates.push(placePoint(map, calculationPoint.startPointScheduleCalculation, "blackLocation" ));
    transportPoints.forEach(point => {
        coordinates.push(placePoint(map, point, "blueLocation"));
    });
    coordinates.push(placePoint(map, calculationPoint.endPoint, "blackLocation" ));
    return coordinates;
}

function roundPDF(transportRoundId){
    Wiistock.download(Routing.generate('print_round_note', {transportRound: transportRoundId}));
}
function placePoint(map, point, icon = "blackLocation") {
    const latitude = point.latitude;
    const longitude = point.longitude;

    map.setMarker({
        latitude,
        longitude,
        icon,
        popUp: map.createPopupContent({contact: point.name}, point.priority),
        name: point.name,
    });
    return [latitude, longitude];
}
