import {initReserveForm} from "@app/pages/truck-arrival/reserve";
import {POST} from "@app/ajax";

$(function () {
    const $reserveModals = $('.reserveModal');
    initReserveForm($reserveModals)

    $reserveModals.each(function (index , $modal) {
        Form
            .create($modal)
            .submitTo( POST, 'reserve_form_submit', {success: () => {
                    location.reload();
                }
            })
    });
});
