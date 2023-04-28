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

    initTableShippings();
})


function initTableShippings() {
    let initialVisible = $(`#tableShippings`).data(`initial-visible`);
    if (!initialVisible) {
        return $
            .post(Routing.generate('shipping_api_columns'))
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
                url: Routing.generate('shipping_api', true),
                type: "POST",
            },
            rowConfig: {
                needsRowClickAction: true,
            },
            columns,
            hideColumnConfig: {
                columns,
                tableFilter: 'tableShippings'
            },
            drawConfig: {
                needsSearchOverride: true,
            },
            page: 'shipping-request',
        };
        tableShippings = initDataTable('tableShippings', tableShippingsConfig);
        return tableShippings;
    }
}
