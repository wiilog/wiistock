import {showAndRequireInputByType} from "@app/utils";
import AJAX, {GET, POST} from "@app/ajax";
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
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    getUserFiltersByPage(PAGE_EMERGENCIES);

    const $table = initializeTable();
    initializeModals($table);
});

/**
 * @param {jQuery} $modal
 * @param {"create"|"edit"} requiredAction
 */
function onEmergencyTypeChange($modal, requiredAction) {
    const $emergencyTypeSelect = $modal.find('[name="type"]')
    const $selectedOption = $emergencyTypeSelect.find('option:selected');
    const emergencyCategoryType = $selectedOption.data('category-type');

    const $stockEmergencyContainer = $modal.find('.stock-emergency-container');
    const $trackingEmergencyContainer = $modal.find('.tracking-emergency-container');
    const $dateContainer = $modal.find('.date-container');
    const $freeFieldsGlobalContainer = $modal.find('.free-fields-global-container');
    const $freeFieldsContainer = $modal.find('.free-fields-container');

    $stockEmergencyContainer.toggleClass('d-none', emergencyCategoryType !== STOCK_EMERGENCY);
    $trackingEmergencyContainer.toggleClass('d-none', emergencyCategoryType !== TRACKING_EMERGENCY);
    $dateContainer.toggleClass('d-none', emergencyCategoryType !== TRACKING_EMERGENCY);
    $freeFieldsGlobalContainer.toggleClass('d-none', emergencyCategoryType === undefined);

    toggleRequiredChampsLibres($emergencyTypeSelect, requiredAction, $freeFieldsContainer);
    typeChoice($emergencyTypeSelect, $freeFieldsContainer);

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

    const remainingQuantitySwitch = $modal.find('[name="endEmergencyCriteria"][value="remaining_quantity"]');
    remainingQuantitySwitch
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
 * @param {JQueryDataTableApi} $tableEmergencies
 */
function initializeModals($tableEmergencies) {
    let $modalNewEmergency = $('#modalNewEmergency');
    Form
        .create($modalNewEmergency, {resetView: ['open', 'close']})
        .on('change', '[name="type"]', () => {
            onEmergencyTypeChange($modalNewEmergency, 'create');
        })
        .on('change', '[name="emergencyTrigger"], [name="endEmergencyCriteria"]', () => {
            onEmergencyTriggerChange($modalNewEmergency);
        })
        .onOpen(() => {
            onEmergencyTypeChange($modalNewEmergency, 'create');
        })
        .onSubmit((data, form) => {
            onNewEmergencySubmit(form, data, $tableEmergencies);
        });

    let $modalEditEmergency = $('#modalEditEmergency');
    Form
        .create($modalEditEmergency, {resetView: ['open', 'close']})
        .onOpen((event) => {
            const emergencyId = $(event.relatedTarget).data('id');
            Modal.load('emergency_edit_api', {emergency: emergencyId}, $modalEditEmergency, $modalEditEmergency.find('.modal-body'), {
                onOpen: () => {
                    onEmergencyTypeChange($modalEditEmergency, 'edit');
                    onEmergencyTriggerChange($modalEditEmergency);
                }
            });
        })
        .submitTo(POST, 'emergency_edit', {
            clearFields: true,
            tables: $tableEmergencies,
        });

    $(document).on('click', '.close-emergency', (event) => {
        const emergency = $(event.target).data('id');
        onCloseEmergency(emergency, $tableEmergencies);
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

/**
 * @param {FormData} data
 * @param {Form} form
 * @param {JQueryDataTableApi} $tableEmergencies
 */
function onNewEmergencySubmit(form,
                              data,
                              $tableEmergencies) {

    const $modal = form.element;
    const $type = $modal.find('[name="type"]');
    const $selectedTypeOption = $type.find('option:selected');
    const emergencyCategoryType = $selectedTypeOption.data('category-type');

    if (emergencyCategoryType === STOCK_EMERGENCY
        && data.has("supplier")) {

        const $table = $('<table/>');

        Modal.confirm({
            message: $('<div>', {
                html: [
                    '<p>Vous allez créer une urgence qui impactera les références suivantes :</p>',
                    '<br/><br/>',
                    $table
                ]
            }),
            title:  "Confirmation de la création de l'urgence",
            validateButton: {
                color: 'success',
                label: 'Confirmer',
            },
            onSuccess: () => {
                form.loading(() => submitNewEmergency(form, data, $tableEmergencies));
            }
        });

        initDataTable($table, {
            ajax: {
                "url": Routing.generate('ajax_select_references', {
                    supplier: data.get("supplier"),
                    "data-key": "data",
                }),
                "type": GET,
            },
            domConfig: {
                removeInfo: true,
                removeLength: true,
                removeTableHeader: true,
            },
            processing: true,
            rowConfig: {
                needsRowClickAction: true
            },
            columns: [
                {data: 'barCode', title: 'Code barre'},
                {data: 'text', title: 'Référence'},
                {data: 'label', title: 'Libellé'},
            ],
            ordering: false,
        });
    }
    else {
        form.loading(() => submitNewEmergency(form, data, $tableEmergencies));
    }
}

/**
 * @param {Form} form
 * @param {FormData} data
 * @param {JQueryDataTableApi} $tableEmergencies
 */
function submitNewEmergency(form,
                            data,
                            $tableEmergencies) {
    return AJAX.route(POST, 'emergency_new')
        .json(data)
        .then(({success}) => {
            if (success) {
                form.element.modal(`hide`);
                form.clear();
                $tableEmergencies.ajax.reload();
            }
        });
}

/**
 * @param {int} emergency
 * @param {JQueryDataTableApi} $tableEmergencies
 */
function onCloseEmergency(emergency,
                          $tableEmergencies) {
    Modal.confirm({
        ajax: {
            method: POST,
            route: 'emergency_close',
            params: {
                emergency
            },
        },
        message: "Voulez-vous vraiment clôturer l'urgence ?",
        title:  "Clôturer l'urgence",
        validateButton: {
            color: 'success',
            label: Translation.of('Général', null, 'Modale', 'Oui'),
        },
        table: $tableEmergencies,
    });
}
