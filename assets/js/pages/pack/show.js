import '@styles/details-page.scss';
import '@styles/pages/pack/timeline.scss';
import {getTrackingHistory} from "@app/pages/pack/common";


$(function() {
    const logisticUnitId = $(`[name="logisticUnitId"]`).val();
    getTrackingHistory(logisticUnitId, true);
});
