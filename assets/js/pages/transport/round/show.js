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
});

