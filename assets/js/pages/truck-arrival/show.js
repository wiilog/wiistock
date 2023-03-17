import {initReserveForm} from "@app/pages/truck-arrival/reserve";
import {POST} from "@app/ajax";

global.editTruckArrival = editTruckArrival;

const $modalEdit = $('#editTruckArrivalModal');

$(function () {
    Form
        .create($modalEdit)
        .submitTo(POST, 'truck_arrival_form_submit');

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

function editTruckArrival(id) {
    Modal.load('truck_arrival_form_edit', {id}, $modalEdit);
}
