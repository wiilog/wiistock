import '@styles/details-page.scss';
import '@styles/pages/pack/timeline.scss';
import {POST} from "@app/ajax";
import Routing from "@app/fos-routing";

$(function() {
    const logisticUnitId = $(`[name="logisticUnitId"]`).val();
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
