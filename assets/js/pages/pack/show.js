import {GET, POST} from "@app/ajax";
import Routing from "@app/fos-routing";

$(function() {
    const logisticUnitId = $(`[name="logisticUnitId"]`).val();
    getTrackingHistory(logisticUnitId);
});

function getTrackingHistory(logisticUnitId) {
    const tableLuhistoryConfig = {
        processing: true,
        serverSide: true,
        paging: true,
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
