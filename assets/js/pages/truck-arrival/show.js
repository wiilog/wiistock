global.editTruckArrival = editTruckArrival;
$(function () {
    console.log('showTruckArrival');
});

function editTruckArrival(id) {
    const $modal = $('#editTruckArrivalModal');
    $modal.modal('show');
}

