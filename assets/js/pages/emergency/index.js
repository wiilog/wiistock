import {showAndRequireInputByType} from "@app/utils";
import {POST} from "@app/ajax";
import Form from "@app/form";
import Modal from "@app/modal";
import FixedFieldEnum from "@generated/fixed-field-enum";
import {initDataTable} from "@app/datatable";
import Routing from "@app/fos-routing";

const TRACKING_EMERGENCY = 'trackingEmergency';
const STOCK_EMERGENCY = 'stockEmergency';
const EMERGENCY_TRIGGER_SUPPLIER = 'supplier';
const EMERGENCY_TRIGGER_REFERENCE = 'reference';
const END_EMERGENCY_CRITERIA_MANUAL = 'manual';
const END_EMERGENCY_CRITERIA_REMAINING_QUANTITY = 'remaining_quantity';
const END_EMERGENCY_CRITERIA_END_DATE = 'end_date';

$(function() {
    const table = initializeTable();
    initializeModals(table);
});

function onEmergencyTypeChange($modal) {
    const $emergencyTypeSelect = $modal.find('[name="type"]')
    const $selectedOption = $emergencyTypeSelect.find('option:selected');
    const emergencyCategoryType = $selectedOption.data('category-type');

    const $stockEmergencyContainer = $modal.find('.stock-emergency-container');
    const $trackingEmergencyContainer = $modal.find('.tracking-emergency-container');
    const $dateContainer = $modal.find('.date-container');

    $stockEmergencyContainer.toggleClass('d-none', emergencyCategoryType !== STOCK_EMERGENCY);
    $trackingEmergencyContainer.toggleClass('d-none', emergencyCategoryType !== TRACKING_EMERGENCY);
    $dateContainer.toggleClass('d-none', emergencyCategoryType !== TRACKING_EMERGENCY);

    if(emergencyCategoryType === STOCK_EMERGENCY) {
        onEmergencyTriggerChange($modal);
    }
    showAndRequireInputByType($emergencyTypeSelect);
}

function onEmergencyTriggerChange($modal) {
    const $emergencyTriggerSwitch = $modal.find('[name="emergencyTrigger"]:checked');
    const selectedTriggerValue = $emergencyTriggerSwitch.val();
    const $endEmergencyCriteriaSwitch = $modal.find('[name="endEmergencyCriteria"]:checked');
    const selectedEndCriteriaValue = $endEmergencyCriteriaSwitch.val();

    const remainingQuantiySwitch = $modal.find('[name="endEmergencyCriteria"][value="remaining_quantity"]');
    remainingQuantiySwitch
        .next()
        .toggleClass('d-none', selectedTriggerValue !== EMERGENCY_TRIGGER_REFERENCE);

    const $dateContainer = $modal.find('.date-container');

    const $referenceSelect = $modal.find('[name="reference"]').closest('div');
    const $quantitySelect = $modal.find('[name="remaining_quantity"]').closest('div');
    const $supplierSelect = $modal.find('.stock-emergency-container  [name="supplier"]').closest('div');
    const $manualDateStartSelect = $modal.find('[name="manual"]').closest('div');

    $referenceSelect.toggleClass('d-none', selectedTriggerValue !== EMERGENCY_TRIGGER_REFERENCE);
    $supplierSelect.toggleClass('d-none', selectedTriggerValue !== EMERGENCY_TRIGGER_SUPPLIER);
    $quantitySelect.toggleClass('d-none', selectedTriggerValue !== EMERGENCY_TRIGGER_REFERENCE || selectedEndCriteriaValue !== END_EMERGENCY_CRITERIA_REMAINING_QUANTITY);
    $dateContainer.toggleClass('d-none', selectedEndCriteriaValue !== END_EMERGENCY_CRITERIA_END_DATE);
    $manualDateStartSelect.toggleClass('d-none', selectedEndCriteriaValue !== END_EMERGENCY_CRITERIA_MANUAL);
}

/**
 * @param {JQueryDataTableApi|JQueryDataTableApi[]} $tableEmergencies
 */
function initializeModals($tableEmergencies) {
    let $modalNewEmergency = $('#modalNewEmergency');
    Form
        .create($modalNewEmergency, {resetView: ['open', 'close']})
        .on('change', '[name="type"]', () => {
            onEmergencyTypeChange($modalNewEmergency);
        })
        .on('change', '[name="emergencyTrigger"], [name="endEmergencyCriteria"]', () => {
            onEmergencyTriggerChange($modalNewEmergency);
        })
        .onOpen(() => {
            onEmergencyTypeChange($modalNewEmergency);
        })
        .submitTo(POST, 'emergency_new', {
            tables: $tableEmergencies,
            clearFields: true,
        });

    let $modalEditEmergency = $('#modalEditEmergency');
    Form
        .create($modalEditEmergency, {resetView: ['open', 'close']})
        .onOpen((event) => {
            const emergencyId = $(event.relatedTarget).data('id');
            Modal.load('emergency_edit_api', {emergency: emergencyId}, $modalEditEmergency, $modalEditEmergency.find('.modal-body'), {
                onOpen: () => {
                    $modalEditEmergency
                        .find('.modal-body')
                        .off('change.emergencyType')
                        .on('change.emergencyType', '[name="type"]', () => {
                            onEmergencyTypeChange($modalEditEmergency);
                        });

                    onEmergencyTypeChange($modalEditEmergency);
                    onEmergencyTriggerChange($modalEditEmergency);
                }
            });
        })
        .submitTo(POST, 'emergency_edit', {
            clearFields: true,
            tables: $tableEmergencies,
        });
}

function initializeTable() {
    return initDataTable('tableEmergency', {
        pageLength: 10,
        processing: true,
        serverSide: true,
        paging: true,
        order: [
            [FixedFieldEnum.dateStart.name, "desc"]
        ],
        ajax: {
            url: Routing.generate('emergency_api_list', true),
            type: POST,
        },
        drawConfig: {
            needsResize: true,
            hidePaging: false,
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        page: 'emergency',
    });
}
