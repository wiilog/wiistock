import '@styles/pages/transport/common.scss';
import '@styles/pages/transport/round-plan.scss'
import {Map} from '@app/map';
import {getStatusHistory} from "@app/pages/transport/common";

import AJAX, {GET, POST} from "@app/ajax";
import Flash, {ERROR} from "@app/flash";
import {transportPDF} from "@app/pages/transport/request/common";

$(function () {
    const map = Map.create(`map`);

    const transportRound= $(`input[name=transportId]`).val();
    const transportType = $(`input[name=transportType]`).val();

    getStatusHistory(transportRound, transportType);

    $('.print-round-button').on('click', function (){
        roundPDF($(this).data('round-id'));
    });
});

function roundPDF(transportRoundId){
    Wiistock.download(Routing.generate('print_round_note', {transportRound: transportRoundId}));
}
