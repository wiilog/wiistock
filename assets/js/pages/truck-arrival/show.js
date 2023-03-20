import AJAX from "@app/ajax";

$(function () {

    let modalDeleteTruckArrival = $('#modalDeleteTruckArrival');
    Form.create(modalDeleteTruckArrival).onSubmit((data, form) => {
        form.loading(() => {
            return AJAX
                .route(AJAX.POST, `truck_arrival_delete`, {truckArrival: $('#truckArrivalId').val()})
                .json(data)
                .then((response) => {
                    if (response.success ) {
                        window.location.href = response.redirect;
                    }
                })
        });
    });

});
