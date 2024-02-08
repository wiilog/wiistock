import AJAX, {POST, GET} from "@app/ajax";
import Camera from "@app/camera";
import Form from "@app/form";

let tableProduction;

global.onProductionRequestTypeChange = onProductionRequestTypeChange;
global.displayAttachmentRequired = displayAttachmentRequired;

let camera = null;

$(function () {
    const $modalNewProductionRequest = $(`#modalNewProductionRequest`);
    Camera.init($modalNewProductionRequest.find(`.take-picture-modal-button`))
        .then(function (instance) {
            camera = instance;
        });
    initProductionRequestsTable().then((table) => {
        tableProduction = table;
        initProductionRequestModal($modalNewProductionRequest, `production_request_new`);
    });

    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    const filtersContainer = $('.filters-container');
    Select2Old.init(filtersContainer.find('.filter-select2[name="multipleTypes"]'), Translation.of('Demande', 'Acheminements', 'Général', 'Types', false));
    Select2Old.init(filtersContainer.find('.filter-select2[name="emergencyMultiple"]'), Translation.of('Demande', 'Général', 'Urgences', false));

    filtersContainer.find('.statuses-filter [name*=statuses-filter]').on('change', function () {
        updateSelectedStatusesCount($(this).closest('.statuses-filter').find('[name*=statuses-filter]:checked').length);
    })

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    getUserFiltersByPage(PAGE_PRODUCTION);

    $(`.export-button`).on(`click`, function () {
        exportFile(`production_request_export`, {}, {
            needsAllFilters: true,
            needsDateFormatting: true,
            $button: $(this),
        });
    });
});

function initProductionRequestsTable() {
    const $filtersContainer = $(".filters-container");
    const $typeFilter = $filtersContainer.find(`select[name=multipleTypes]`);

    let initialVisible = $(`#tableProduction`).data(`initial-visible`);

    let status = $filtersContainer.find(`.statuses-filter [name*=statuses-filter]:checked`)
        .map((index, line) => $(line).data('id'))
        .toArray();

    updateSelectedStatusesCount(status.length);

    let pathProduction = Routing.generate('production_request_api', {
        filterStatus: status,
        preFilledTypes: $typeFilter.val()
    }, true);

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
                url: pathProduction,
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

function onProductionRequestTypeChange($select){
    onTypeChange($select);
    const $modal = $select.closest(`.modal`);
    const $typeSelect = $modal.find(`[name=type]`);
    const $selectStatus = $modal.find(`[name=status]`);
    const $selectDropLocation = $modal.find(`[name=dropLocation]`);

    $selectStatus.prop(`disabled`, !Boolean($typeSelect.val()));

    const optionData = $typeSelect.select2('data').length > 0 ? $typeSelect.select2('data')[0] : {};
    const defaultStatus = optionData['defaultStatus'];
    if(defaultStatus){
        const [id, value] = defaultStatus.split(':');
        $selectStatus
            .append(new Option(value, id, true, true))
            .trigger(`change`);
    }

    if (optionData.dropLocationId && optionData.dropLocationLabel) {
        $selectDropLocation.append(new Option(optionData.dropLocationLabel, optionData.dropLocationId, true, true)).trigger(`change`);
    }
    $selectDropLocation.attr('data-other-params-typeDispatchDropLocation', $typeSelect.val() || "")
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
        .onOpen(() => {
            const $takePictureModalButton = $modal.find(`.take-picture-modal-button`);
            if(camera) {
                $takePictureModalButton
                    .off(`click.productionRequestTakePicture`)
                    .on(`click.productionRequestTakePicture`, function () {
                        wrapLoadingOnActionButton($(this), () => camera.open($modal.find(`[name="files[]"]`)));
                    });
            }
        })
        .submitTo(POST, submitRoute, {
            tables: [tableProduction],
        });
}
