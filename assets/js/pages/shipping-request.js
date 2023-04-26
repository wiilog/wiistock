let tableShippings;

$(function() {
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
