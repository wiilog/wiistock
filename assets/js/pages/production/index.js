let tableProduction;

global.onProductionRequestTypeChange = onProductionRequestTypeChange;
global.displayAttachmentRequired = displayAttachmentRequired;

$(function () {
    initTableShippings().then((table) => {
        tableProduction = table;
        initProductionRequestModal($(`#modalNewProductionRequest`), `production_request_new`);
    });
});
function initTableShippings() {
    let initialVisible = $(`#tableProduction`).data(`initial-visible`);
    if (!initialVisible) {
        return AJAX
            .route(AJAX.GET, 'production_request_api_columns')
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
                type: AJAX.POST,
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

function onProductionRequestTypeChange($select){
    const $modal = $select.closest(`.modal`);
    const $typeSelect = $modal.find(`[name=type]`);
    const $selectStatus = $modal.find(`[name=status]`);

    $selectStatus.prop(`disabled`, !Boolean($typeSelect.val()))
    $selectStatus.val(null).trigger(`change`);
}

function displayAttachmentRequired($select) {
    const $modal = $select.closest(`.modal`);
    const statusData = $select.select2(`data`)[0];

    const requiredAttachment = statusData && statusData.requiredAttachment ? 1 : 0;
    $modal.find(`[name=isFileNeeded]`).val(requiredAttachment);
    $modal.find(`[name=isSheetFileNeeded]`).val(requiredAttachment);
}


function initProductionRequestModal($modal, submitRoute) {
    Form
        .create($modal, {clearOnOpen: true})
        .submitTo(AJAX.POST, submitRoute, {
            tables: [tableProduction],
        });
}
