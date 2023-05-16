import {GET} from "@app/ajax";

let tableShippings;


$(function() {
    Select2Old.init($('.filters select[name="carriers"]'), 'Transporteurs');
    initDateTimePicker('#dateMin, #dateMax');

    let params = GetRequestQuery();
    // applique les filtres si pré-remplis
    let val = $('#filterStatus').val();

    if (params.date || val && val.length > 0) {
        if(val && val.length > 0) {
            let valuesStr = val.split(',');
            let valuesInt = [];
            valuesStr.forEach((value) => {
                valuesInt.push(parseInt(value));
            })
            $('#statut').val(valuesInt).select2();
        }
    } else {
        // sinon, filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_SHIPPING);
        $.post(path, params, function (data) {
            displayFiltersSup(data, true);
        }, 'json');
    }

    initTableShippings().then((table) => {
        tableShippings = table;
    });
    initScheduledShippingRequestForm();
})

function initTableShippings() {
    let initialVisible = $(`#tableShippings`).data(`initial-visible`);
    if (!initialVisible) {
        return AJAX
            .route(GET, 'shipping_request_api_columns')
            .json()
            .then(columns => proceed(columns));
    } else {
        return new Promise((resolve) => {
            resolve(proceed(initialVisible));
        });
    }
    function proceed(columns) {
        let tableShippingsConfig = {
            pageLength: 10,
            processing: true,
            serverSide: true,
            paging: true,
            ajax: {
                url: Routing.generate('shipping_request_api', true),
                type: "GET",
            },
            rowConfig: {
                needsRowClickAction: true,
            },
            columns: columns,
            hideColumnConfig: {
                columns,
                tableFilter: 'tableShippings'
            },
            drawConfig: {
                needsSearchOverride: true,
            },
            page: 'shippingRequest',
        };

        return initDataTable('tableShippings', tableShippingsConfig);
    }
}
