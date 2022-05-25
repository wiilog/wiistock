import '@styles/pages/transport/common.scss';
import '@styles/pages/transport/round-plan.scss'
import {Map} from '@app/map';
import {getStatusHistory, getTransportRoundTimeline} from "@app/pages/transport/common";

import AJAX, {GET, POST} from "@app/ajax";
import Flash, {ERROR} from "@app/flash";



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
