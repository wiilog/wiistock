import {POST} from "@app/ajax";

global.editTruckArrival = editTruckArrival;

const $modalEdit = $('#editTruckArrivalModal');

$(function () {
    Form
        .create($modalEdit)
        .submitTo(POST, 'truck_arrival_form_submit');
});

function editTruckArrival(id) {
    Modal.load('truck_arrival_form_edit', {id}, $modalEdit);
    $modal.modal('show');
}

