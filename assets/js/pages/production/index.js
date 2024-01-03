import {GET, POST} from "@app/ajax";

let tableProduction;
$(function () {
    initTableShippings().then((table) => {
        tableProduction = table;
    });
});
function initTableShippings() {
    let initialVisible = $(`#tableProduction`).data(`initial-visible`);
    if (!initialVisible) {
        return AJAX
            .route(GET, 'production_request_api_columns')
            .json()
            .then(columns => proceed(columns));
    } else {
        return new Promise((resolve) => {
            resolve(proceed(initialVisible));
        });
    }
    function proceed(columns) {
        let tableProductionConfig = {
            pageLength: 10,
            processing: true,
            serverSide: true,
            paging: true,
            order: [['number', "desc"]],
            ajax: {
                url: Routing.generate('production_request_api', true),
                type: POST,
            },
            rowConfig: {
                needsRowClickAction: true,
                needsColor: true,
                color: 'danger',
                dataToCheck: 'emergency',
            },
            columns: columns,
            hideColumnConfig: {
                columns,
                tableFilter: 'tableProductions',
            },
            drawConfig: {
                needsSearchOverride: true,
            },
            page: 'productionRequest',
        };

        return initDataTable('tableProductions', tableProductionConfig);
    }
}
