import '@styles/details-page.scss';
import '@styles/pages/pack/timeline.scss';
import {POST} from "@app/ajax";
import Routing from "@app/fos-routing";

$(function() {
    const logisticUnitId = $(`[name="logisticUnitId"]`).val();
    getMovementsHistory(logisticUnitId, true);
    getTrackingHistory(logisticUnitId, true);
});

export function getTrackingHistory(logisticUnitId, searchable = true) {
    const tableLuhistoryConfig = {
        processing: true,
        serverSide: true,
        paging: true,
        searching: searchable,
        ajax: {
            url: Routing.generate(`pack_tracking_history_api`, {id: logisticUnitId}, true),
            type: POST,
        },
        columns: [
            {data: `history`, title: ``, orderable: false},
        ],
    };
    initDataTable($('#table-LU-history'), tableLuhistoryConfig);
}


function getMovementsHistory(logisticUnitId, searchable = true) {
    const tableLuMovementHistoryConfig = {
        processing: true,
        serverSide: true,
        lengthMenu: [10],
        paging: true,
        searching: false,
        ajax: {
            url: Routing.generate(`pack_movements_history_api`, {id: logisticUnitId}, true),
            type: POST,
        },
        columns: [
            {data: `movementDate`, title: `Date`, orderable: false},
            {data: `trackingEvent`, title: `Type`, orderable: false},
            {data: `delay`, title: `Délai`, orderable: false},
            {data: `location`, title: `Emplacement`, orderable: false},
            {data: `nature`, title: `Nature`, orderable: false},
        ],
    };
    initDataTable($('#table-Ul-tracking-delay-records'), tableLuMovementHistoryConfig);
}
