import '@styles/pages/transport/show.scss';
import "@app/pages/transport/common-show";

$(function () {
    const $modalCollectTimeSlot = $("#modalCollectTimeSlot");
    InitModal(
        $modalCollectTimeSlot,
        $modalCollectTimeSlot.find('.submit-button'),
        Routing.generate('validate_time_slot', true), {
        success: () => {
            window.location.reload();
        }}
    );
});
