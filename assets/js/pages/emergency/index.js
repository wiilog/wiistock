import {showAndRequireInputByType} from "@app/utils";

let tableEmergencies;
const TRACKING_EMERGENCY = 'trackingEmergency';
const STOCK_EMERGENCY = 'stockEmergency';
const EMERGENCY_TRIGGER_SUPPLIER = 'supplier';
const EMERGENCY_TRIGGER_REFERENCE = 'reference';
const END_EMERGENCY_CRITERIA_MANUAL = 'manual';
const END_EMERGENCY_CRITERIA_REMAINING_QUANTITY = 'remaining_quantity';
const END_EMERGENCY_CRITERIA_END_DATE = 'end_date';

$(function() {

    let $modalNewEmergency = $('#modalNewEmergency');
    Form
        .create($modalNewEmergency)
        .on('change', '[name="type"]', () => {
            onEmergencyTypeChange($modalNewEmergency);
        })
        .on('change', '[name="emergencyTrigger"], [name="endEmergencyCriteria"]', () => {
            onEmergencyTriggerChange($modalNewEmergency);
        })
        .onOpen(() => {
            onEmergencyTypeChange($modalNewEmergency);
        })
        .submitTo(AJAX.POST, 'emergency_new', {
            tables: tableEmergencies
        });

    let $modalEditEmergency = $('#modalEditEmergency');
    Form
        .create($modalEditEmergency)
        .onOpen((event) => {
            Modal.load('emergency_edit_api', {emergency: "7"}, $modalEditEmergency, $modalEditEmergency.find('.modal-body'), {
                onOpen: () => {
                    $modalEditEmergency
                        .find('.modal-body')
                        .on('change', '[name="type"]', () => {
                            onEmergencyTypeChange($modalEditEmergency);
                        });

                    $modalEditEmergency.find('[name="type"]').trigger('change');
                    onEmergencyTriggerChange($modalEditEmergency);
                }
            });
        })
        .submitTo(AJAX.POST, 'emergency_edit', {
            routeParams: {id: "7"},
            tables: tableEmergencies,
        });
    $modalEditEmergency.modal('show');
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
