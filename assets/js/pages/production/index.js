import AJAX, {POST, GET} from "@app/ajax";
import Camera from "@app/camera";
import Modal from "@app/modal";
import Routing from '@app/fos-routing';
import {displayAttachmentRequired, initDeleteProductionRequest, initModalNewProductionRequest} from './form'
import {getUserFiltersByPage} from '@app/utils';
import {initDataTable} from "@app/datatable";
import {showAndRequireInputByType} from "@app/utils";
import {exportFile} from "@app/utils";

let tableProduction;

global.displayAttachmentRequired = displayAttachmentRequired;

$(function () {
    initProductionRequestsTable().then((table) => {
        tableProduction = table;

        initNewProductionRequest();
        initDuplicateProductionRequest();
    });

    initDeleteProductionRequest();

    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    const filtersContainer = $('.filters-container');
    Select2Old.init(filtersContainer.find('.filter-select2[name="multipleTypes"]'), Translation.of('Demande', 'Acheminements', 'Général', 'Types', false));
    Select2Old.init(filtersContainer.find('.filter-select2[name="emergencyMultiple"]'), Translation.of('Demande', 'Général', 'Urgences', false));

    filtersContainer.find('.statuses-filter [name*=statuses-filter]').on('change', function () {
        updateSelectedStatusesCount($(this).closest('.statuses-filter').find('[name*=statuses-filter]:checked').length);
    })

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    const fromDashboard = $('[name="fromDashboard"]').val() === '1';

    getUserFiltersByPage(PAGE_PRODUCTION, {preventPrefillFilters: fromDashboard});

    $(`.export-button`).on(`click`, function () {
        exportFile(`production_request_export`, {}, {
            needsAllFilters: true,
            needsDateFormatting: true,
            $button: $(this),
        });
    });


    const $dispatchModeContainer = $('.dispatch-mode-container');
    const $dispatchButtonContainer = $('.dispatch-button-container');
    const $filtersInputs = filtersContainer.find(`select, input, button, .checkbox-filter`);
    $(`.dispatch-button`).on(`click`, function () {
        $(this).pushLoader(`black`);
        tableProduction.clear().destroy();
        initProductionRequestsTable(true).then((returnedProductionsTable) => {
            tableProduction = returnedProductionsTable;
            $(`.dataTables_filter`).parent().remove();
            $dispatchModeContainer.removeClass(`d-none`);
            $dispatchButtonContainer.addClass(`d-none`);
            $filtersInputs.prop(`disabled`, true).addClass(`disabled`);
            $(this).popLoader();
        });
    });

    $dispatchModeContainer.find(`.cancel`).on(`click`, function () {
        $dispatchModeContainer.find(`.validate`).prop(`disabled`, true);
        $(this).pushLoader(`primary`);
        tableProduction.clear().destroy();
        initProductionRequestsTable(false).then((returnedProductionsTable) => {
            tableProduction = returnedProductionsTable;
            $dispatchButtonContainer.removeClass(`d-none`);
            $dispatchModeContainer.addClass(`d-none`);
            $filtersInputs.prop(`disabled`, false).removeClass(`disabled`);
            $(this).popLoader();
        });
    });

    const $productionsTable = $(`#tableProductions`);
    let $modalNewDispatch = $("#modalNewDispatch");

    $dispatchModeContainer.find(`.validate`).on(`click`, function () {
        const $checkedCheckboxes = $productionsTable.find(`input[type=checkbox]:checked`).not(`.check-all`);
        const productionsToDispatch = $checkedCheckboxes.toArray().map((element) => $(element).val());

        initDispatchCreateForm($modalNewDispatch, 'productions', productionsToDispatch);
        if (productionsToDispatch.length > 0) {
            $modalNewDispatch.modal(`show`);
        }
    });

    $(document).arrive(`.check-all`, function () {
        $(this).on(`click`, function () {
            $productionsTable.find(`.dispatch-checkbox`).not(`:disabled`).prop(`checked`, $(this).is(`:checked`));
            toggleValidateDispatchButton($productionsTable, $dispatchModeContainer);
        });
    });

    $(document).on(`change`, `.dispatch-checkbox:not(:disabled)`, function () {
        toggleValidateDispatchButton($productionsTable, $dispatchModeContainer);
    });
});

function initProductionRequestsTable(dispatchMode = false) {
    const $filtersContainer = $(".filters-container");
    const $typeFilter = $filtersContainer.find(`select[name=multipleTypes]`);

    let initialVisible = $(`#tableProduction`).data(`initial-visible`);

    let status = $filtersContainer.find(`.statuses-filter [name*=statuses-filter]:checked`)
        .map((index, line) => $(line).data('id'))
        .toArray();

    updateSelectedStatusesCount(status.length);

    const fromDashboard = $('[name="fromDashboard"]').val() === '1';

    let pathProduction = Routing.generate('production_request_api', {
        filterStatus: status,
        preFilledTypes: $typeFilter.val(),
        fromDashboard,
        dispatchMode,
    }, true);

    if (dispatchMode || !initialVisible) {
        return AJAX
            .route(GET, 'production_request_api_columns',  {dispatchMode})
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
            serverSide: !dispatchMode,
            paging: true,
            order: [['number', "desc"]],
            ajax: {
                url: pathProduction,
                type: POST,
            },
            drawConfig: {
                needsResize: true,
                hidePaging: dispatchMode,
            },
            disabledRealtimeReorder: dispatchMode,
            rowConfig: {
                needsRowClickAction: true,
                needsColor: true,
                color: 'danger',
                dataToCheck: 'emergency',
            },
            columns: columns,
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
    $selectDropLocation.attr('data-other-params-typeDispatchDropLocation', $typeSelect.val() || "");

    showAndRequireInputByType($select);
    updateExpectedAtField($modal);
}

function initNewProductionRequest() {
    const $modal = $(`#modalNewProductionRequest`);
    initModalNewProductionRequest(
        $modal,
        [tableProduction],
        (event) => {
            Camera.init(
                $modal.find(`.take-picture-modal-button`),
                $modal.find(`[name="files[]"]`)
            );
        }
    );

    $modal.find('[name="type"]')
        .off('change.initNewProductionRequest')
        .on("change.initNewProductionRequest", function() {
            onProductionRequestTypeChange($(this));
        });
}
function initDuplicateProductionRequest() {
    const $modalEditProductionRequest = $(`#modalEditProductionRequest`);
    initModalNewProductionRequest(
        $modalEditProductionRequest,
        [tableProduction],
        (event) => {
            const $button = $(event.relatedTarget);
            const $formContainer = $modalEditProductionRequest.find('.form-production-request');
            Modal.load(
                'production_request_form_duplicate',
                {productionRequest : $button.data('id') },
                $modalEditProductionRequest,
                $formContainer,
                {
                    $formContainer: $formContainer,
                    onOpen: () => {
                        Camera.init(
                            $modalEditProductionRequest.find(`.take-picture-modal-button`),
                            $modalEditProductionRequest.find(`[name="files[]"]`)
                        );
                    }
                }
            );
        }
    )
}

function toggleValidateDispatchButton($productionsTable, $dispatchModeContainer) {
    const $allDispatchCheckboxes = $(`.dispatch-checkbox`).not(`:disabled`);
    const atLeastOneChecked = $allDispatchCheckboxes.toArray().some((element) => $(element).is(`:checked`));

    $dispatchModeContainer.find(`.validate`).prop(`disabled`, !atLeastOneChecked);
    $(`.check-all`).prop(`checked`, ($allDispatchCheckboxes.filter(`:checked`).length) === $allDispatchCheckboxes.length);
}

function updateExpectedAtField($modal) {
    const $type = $modal.find('[name="type"]');
    const $expectedAt = $modal.find('input[name=expectedAt]');
    const $expectedAtSettings = $modal.find('input[name="expectedAtSettings"]');

    const settings = JSON.parse($expectedAtSettings.val());

    const selectedType = $type.val();

    let expectedAtMin = null;

    if(selectedType) {
        expectedAtMin = settings[selectedType]
            || settings.all
            || null;
    }

    $expectedAt.attr('min', expectedAtMin);
}


