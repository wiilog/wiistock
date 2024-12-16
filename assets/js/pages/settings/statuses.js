import EditableDatatable, {MODE_CLICK_EDIT, MODE_NO_EDIT, SAVE_MANUALLY, STATE_VIEWING} from "../../editatable";
import AJAX, {GET} from "@app/ajax";
import Routing from '@app/fos-routing';
import {onSelectAll} from "./utils";

const MODE_ARRIVAL_DISPUTE = 'arrival-dispute';
const MODE_RECEPTION_DISPUTE = 'reception-dispute';
const MODE_PURCHASE_REQUEST = 'purchase-request';
const MODE_ARRIVAL = 'arrival';
const MODE_DISPATCH = 'dispatch';
const MODE_HANDLING = 'handling';
const MODE_PRODUCTION = 'production';

const DISABLED_LABELS_TRANSLATION_PAGES = [
    `#reception-dispute-statuses-table`,
    `#purchase-request-statuses-table`
];

const $managementButtons = $(`.save-settings, .discard-settings`);
let canTranslate = true;

const fieldsToDisabledAttr = {
    needsMobileSync: 'need-mobile-sync-disabled',
    passStatusAtPurchaseOrderGeneration: 'pass-status-at-purchase-order-generation-disabled',
    automaticReceptionCreation: 'automatic-reception-creation-disabled',
};

const fieldsMaxChecked = {
    needsMobileSync: undefined,
    color: undefined,
    passStatusAtPurchaseOrderGeneration: 1,
    automaticReceptionCreation: undefined,
};

export function initializeArrivalDisputeStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, MODE_ARRIVAL_DISPUTE);
}

export function initializeReceptionDisputeStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, MODE_RECEPTION_DISPUTE);
}

export function initializePurchaseRequestStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, MODE_PURCHASE_REQUEST);
}

export function initializeDispatchStatuses($container, canEdit) {
    initializeStatusesByTypes($container, canEdit, MODE_DISPATCH)
}

export function initializeArrivalStatuses($container, canEdit) {
    initializeStatusesByTypes($container, canEdit, MODE_ARRIVAL)
}

export function initializeProductionStatuses($container, canEdit) {
    initializeStatusesByTypes($container, canEdit, MODE_PRODUCTION)
}

export function initializeHandlingStatuses($container, canEdit) {
    initializeStatusesByTypes($container, canEdit, MODE_HANDLING)
}

function initializeStatuses($container, canEdit, mode, categoryType) {
    const $addButton = $container.find(`.add-row-button`);
    const $translateButton = $container.find(`.translate-labels-button`);
    const $filtersContainer = $container.find('.filters-container');
    const $pageBody = $container.find('.page-body');
    const $addRow = $container.find(`.add-row`);
    const $translateLabels = $container.find('.translate-labels');
    const $statusStateOptions = $container.find('[name=status-state-options]');
    const statusStateOptions = JSON.parse($statusStateOptions.val());
    const $groupedSignatureTypes = $container.find('[name=grouped-signature-types]');
    const groupedSignatureTypes = $groupedSignatureTypes.val() ? JSON.parse($groupedSignatureTypes.val()) : '';
    const $roleOptions = $container.find('[name=role-options]');
    const roleOptions = $roleOptions.val() ? JSON.parse($roleOptions.val()) : '';
    const hasRightGroupedSignature = $container.find('[name=has-right-grouped-signature]').val();
    const tableSelector = `#${mode}-statuses-table`;
    const type = $('[name=type]:checked').val();
    const $modalEditTranslations = $container.find(".edit-translation-modal");
    const route = Routing.generate(`settings_statuses_api`, {mode, type});

    const table = EditableDatatable.create(tableSelector, {
        route,
        deleteRoute: `settings_delete_status`,
        mode: canEdit ? MODE_CLICK_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: true,
        ordering: true,
        paging: true,
        scrollY: false,
        scrollX: false,
        onInit: () => {
            $addButton.removeClass(`d-none`);
        },
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
            if(!DISABLED_LABELS_TRANSLATION_PAGES.includes(tableSelector)) {
                $addRow.addClass('d-none');
                if (canTranslate) {
                    $translateLabels.removeClass('d-none');
                }

                $translateButton
                    .off('click.statusTranslation')
                    .on('click.statusTranslation', function () {
                        wrapLoadingOnActionButton($(this), () => (
                            AJAX.route(GET, "settings_edit_status_translations_api", {
                                type: $('[name=type]:checked').val(),
                                mode: mode
                            })
                                .json()
                                .then((response) => {
                                    $modalEditTranslations.find(`.modal-body`).html(response.html);
                                    $modalEditTranslations.modal('show');
                                })
                        ));
                    });
            }
            onStatusStateChange($container, $container.find('[name=state]'));
            ensureMaxCheckboxSelection($container, "passStatusAtPurchaseOrderGeneration");
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
            $addRow.removeClass('d-none');
            $filtersContainer.removeClass('d-none');
            if (canTranslate) { $translateLabels.addClass('d-none'); }
            canTranslate = true;
            $pageBody.find('.wii-title').remove();
        },
        columns: getStatusesColumn(mode, hasRightGroupedSignature),
        form: getFormColumn(mode, statusStateOptions, categoryType, groupedSignatureTypes, hasRightGroupedSignature, roleOptions),
    });

    let submitEditTranslations = $modalEditTranslations.find("[type=submit]");
    let urlEditTranslations = Routing.generate('settings_edit_status_translations', true);
    InitModal($modalEditTranslations, submitEditTranslations, urlEditTranslations, {
        success: () => {
            table.toggleEdit(STATE_VIEWING, true);
        }
    });

    $addRow
        .off('click')
        .on(`click`, function() {
            table.addRow(true);
        });

    $addButton
        .on('click', function() {
            canTranslate = false;
        });

    $container.on('change', '[name=state]', function () {
        onStatusStateChange($container, $(this));
    });

    return table;
}

function getStatusesColumn(mode, hasRightGroupedSignature) {
    const singleRequester = [MODE_DISPATCH, MODE_HANDLING, MODE_PURCHASE_REQUEST, MODE_ARRIVAL_DISPUTE, MODE_PRODUCTION].includes(mode) ? ['', ''] : ['x', 's'];
    const singleBuyer = [MODE_PURCHASE_REQUEST].includes(mode) ? [`à`, `l'acheteur`] : [`aux`, `acheteurs`];

    return [
        {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
        {data: `label`, title: `Libellé`, required: true},
        {data: `state`, title: `État`, required: true},
        {data: `type`, title: `Type`, required: true, modes: [MODE_ARRIVAL, MODE_DISPATCH, MODE_HANDLING, MODE_PRODUCTION], class: `minw-150px`},
        {data: `comment`, title: `Commentaire litige`, modes: [MODE_ARRIVAL_DISPUTE, MODE_RECEPTION_DISPUTE]},
        {
            data: `defaultStatut`,
            title: `<div>Statut<br/>par défaut</div>`,
            modes: [MODE_ARRIVAL, MODE_ARRIVAL_DISPUTE, MODE_RECEPTION_DISPUTE, MODE_HANDLING, MODE_PURCHASE_REQUEST, MODE_PRODUCTION]},
        {
            data: `sendMailBuyers`,
            title: `<div class='small-column'>Envoi d'emails ${singleBuyer[0]} ${singleBuyer[1]}</div>`,
            modes: [MODE_ARRIVAL_DISPUTE, MODE_RECEPTION_DISPUTE, MODE_PURCHASE_REQUEST]
        },
        {
            data: `sendMailRequesters`,
            title: `<div class='small-column'>Envoi d'emails au${singleRequester[0]} demandeur${singleRequester[1]}</div>`,
            modes: [MODE_ARRIVAL_DISPUTE, MODE_RECEPTION_DISPUTE, MODE_HANDLING, MODE_PURCHASE_REQUEST, MODE_DISPATCH, MODE_PRODUCTION]
        },
        {
            data: `sendMailDest`,
            title: `<div class='small-column'>Envoi d'emails aux destinataires</div>`,
            modes: [MODE_HANDLING, MODE_DISPATCH]
        },
        {
            data: `allowedCreationForRoles`,
            title: `<div style="width: 250px !important; white-space: initial !important;">Autorisation de créer au statut par rôle</div>`,
            class: `maxw-250px`,
            modes: [MODE_DISPATCH],
        },
        ...(hasRightGroupedSignature ? [
            {
                data: `sendReport`,
                title: `<div class='small-column'>Envoi compte rendu</div>`,
                modes: [MODE_DISPATCH]
            },
            {
                data: `groupedSignatureType`,
                title: `<div class='small-column'>Signature groupée</div>`,
                modes: [MODE_DISPATCH]
            },
            {
                data: `groupedSignatureColor`,
                title: `<div class='small-column'>Couleur signature groupée</div>`,
                modes: [MODE_DISPATCH]
            }] : []),
        {
            data: `overconsumptionBillGenerationStatus`,
            title: `<div class='small-column'>Passage au statut à la génération du bon de surconsommation</div>`,
            modes: [MODE_DISPATCH]
        },
        {
            data: `preventStatusChangeWithoutDeliveryFees`,
            title: `<div class='small-column' style="max-width: 160px !important;">Blocage du changement de statut si frais de livraison non rempli</div>`,
            modes: [MODE_PURCHASE_REQUEST]
        },
        {
            data: `passStatusAtPurchaseOrderGeneration`,
            title: `<div class='small-column' style="max-width: 160px !important;">Passage au statut à la génération du bon de commande</div>`,
            modes: [MODE_PURCHASE_REQUEST]
        },
        {
            data: `automaticReceptionCreation`,
            title: `<div class='small-column' style="max-width: 160px !important;">Création automatique d'une réception</div>`,
            modes: [MODE_PURCHASE_REQUEST]
        },
        {
            data: `needsMobileSync`,
            title: `<div class='small-column'>Synchronisation nomade</div>`,
            modes: [MODE_HANDLING, MODE_DISPATCH]
        },
        ...(hasRightGroupedSignature
            ? [{
                data: `commentNeeded`,
                title: `<div class='small-column'>Commentaire obligatoire signature groupée</div>`,
                modes: [MODE_DISPATCH]
            }]
            : []),
        {
            data: `displayedOnSchedule`,
            title: `<div class='small-column'>Affichage sur planning</div>`,
            modes: [MODE_PRODUCTION]
        },
        {
            data: `createDropMovementOnDropLocation`,
            title: `<div class='small-column'>Création d’un mouvement de dépose sur l’emplacement de dépose</div>`,
            modes: [MODE_PRODUCTION]
        },
        {
            data: `typeForGeneratedDispatchOnStatusChange`,
            title: `<div class='small-column'>Proposition de générer une demande d'acheminement</div>`,
            modes: [MODE_PRODUCTION]
        },
        {
            data: `notifiedUsers`,
            title: `<div class='small-column'>Utilisateur(s) à notifier</div>`,
            modes: [MODE_PRODUCTION]
        },
        {
            data: `requiredAttachment`,
            title: `<div class='small-column'>PJ obligatoire</div>`,
            modes: [MODE_PRODUCTION]
        },
        {
            data: 'color',
            title: `<div class='small-column'>Couleur</div>`,
            modes: [MODE_PRODUCTION],
        },
        {
            data: `order`,
            title: `<div class='small-column'>Ordre</div>`,
            class: `maxw-70px`,
            required: true
        },
    ].filter(({modes}) => !modes || modes.indexOf(mode) > -1);
}

function getFormColumn(mode, statusStateOptions, categoryType, groupedSignatureTypes, hasRightGroupedSignature, roleOptions){
    return {
        actions: `
            <button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>
            <input type='hidden' name='mode' class='data' value='${mode}'/>
        `,
        label: `<input type='text' name='label' class='form-control data needed select-size' data-global-error="Libellé"/>`,
        state: `<select name='state' class='data form-control needed select-size'>${statusStateOptions}</select>`,
        type: categoryType
            ? `
                <select name='type'
                        style="min-width: 150px"
                        class='form-control data'
                        required
                        data-s2='types' data-parent="body"
                        data-no-search
                        data-min-length="0"
                        data-other-params
                        data-other-params-category='${categoryType}'>
                </select>
            `
            : null,
        comment: `<input type='text' name='comment' class='form-control data'/>`,
        defaultStatut: `<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data'/></div>`,
        sendMailBuyers: `<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data'/></div>`,
        sendMailRequesters: `<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data'/></div>`,
        needsMobileSync: `<div class='checkbox-container'><input type='checkbox' name='needsMobileSync' class='form-control data'/></div>`,
        commentNeeded: hasRightGroupedSignature ? `<div class='checkbox-container'><input type='checkbox' name='commentNeeded' class='form-control data'/></div>` : null,
        sendMailDest: `<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data'/></div>`,
        overconsumptionBillGenerationStatus: `<div class='checkbox-container'><input type='checkbox' name='overconsumptionBillGenerationStatus' class='form-control data'/></div>`,
        sendReport: hasRightGroupedSignature ? `<div class='checkbox-container'><input type='checkbox' name='sendReport' class='form-control data'/></div>` : null,
        groupedSignatureType:  hasRightGroupedSignature ? `<select name='groupedSignatureType' class='data form-control select-size'>${groupedSignatureTypes}</select>` : null,
        groupedSignatureColor: hasRightGroupedSignature ? getInputColor('groupedSignatureColor') : null,
        color: getInputColor('color'),
        preventStatusChangeWithoutDeliveryFees: `<div class='checkbox-container'><input type='checkbox' name='preventStatusChangeWithoutDeliveryFees' class='form-control data'/></div>`,
        automaticReceptionCreation: `<div class='checkbox-container'><input type='checkbox' name='automaticReceptionCreation' class='form-control data'/></div>`,
        passStatusAtPurchaseOrderGeneration: `<div class='checkbox-container'><input type='checkbox' name='passStatusAtPurchaseOrderGeneration' class='form-control data'/></div>`,
        displayedOnSchedule: `<div class='checkbox-container'><input type='checkbox' name='displayedOnSchedule' class='form-control data'/></div>`,
        createDropMovementOnDropLocation: `<div class='checkbox-container'><input type='checkbox' name='createDropMovementOnDropLocation' class='form-control data'/></div>`,
        notifiedUsers: `<select name='notifiedUsers' class='form-control data' multiple data-s2='user'></select>`,
        typeForGeneratedDispatchOnStatusChange: `<select name='typeForGeneratedDispatchOnStatusChange' class='form-control data' data-s2='dispatchType'></select>`,
        requiredAttachment: `<div class='checkbox-container'><input type='checkbox' name='requiredAttachment' class='form-control data'/></div>`,
        order: `<input type='number' name='order' min='1' class='form-control data needed px-2 text-center' data-global-error="Ordre" data-no-arrow/>`,
        allowedCreationForRoles: `<select name='allowedCreationForRoles' class='form-control data' multiple data-s2='roles'>${roleOptions}</select>`,
    };
}

function getInputColor(name) {
    return `
        <input type='color' class='form-control wii-color-picker data' name='${name}' value='#3353D7' list='type-color'/>
        <datalist>
            <option>#D76433</option>
            <option>#D7B633</option>
            <option>#A5D733</option>
            <option>#33D7D1</option>
            <option>#33A5D7</option>
            <option>#3353D7</option>
            <option>#6433D7</option>
            <option>#D73353</option>
        </datalist>
    `
}

function initializeStatusesByTypes($container, canEdit, mode) {
    const categoryType = $container.find('[name=category-type]').val();
    const $typeFilters = $container.find('[name=type]');
    const $addButton = $container.find(`.add-row-button`);
    const $filtersContainer = $container.find('.filters-container');
    const $pageBody = $container.find('.page-body');

    const table = initializeStatuses($container, canEdit, mode, categoryType);

    $typeFilters
        .off('change')
        .on('change', function() {
            const $type = $(this);
            const type = $type.val();
            const url = Routing.generate(`settings_statuses_api`, {mode, type})
            table.setURL(url, true, () => {
                disableFieldsBasedOnStatus($container);
            });
        });
    $typeFilters.first().trigger('click');

    $addButton
        .on('click', function() {
            $filtersContainer.addClass('d-none');
            $pageBody.prepend('<div class="header wii-title">Ajouter des statuts</div>');
        });

    $(document).arrive(`.select-all-options`, function () {
        $(this).on('click', onSelectAll);
    });
}

/**
 * Handles the change event of the status state select element
 * @param {jQuery} $container - Global container of the array containing fields
 * @param {jQuery} $selects - jQuery object representing the select element
 */
function onStatusStateChange($container, $selects) {
    // if $select is an array of select elements (first call of the function)
    $selects.each(function() {
        const $select = $(this);
        const $form = $select.closest('tr');

        // Disable fields based on the selected status
        Object.keys(fieldsToDisabledAttr).forEach((name) => {
            const $field = $form.find(`[name="${name}"]`);
            if (isFieldDisabled($field)) {
                $field
                    .prop('disabled', true)
                    .prop('checked', false);
            }
        });
    });

    // Disable fields based on the selected status
    disableFieldsBasedOnStatus($container);
}

function disableFieldsBasedOnStatus($container) {
    Object.keys(fieldsToDisabledAttr).forEach((name) => {
        const $fields = $container.find(`[name=${name}]`);
        updateCheckboxes(name, $fields);
    });
}

/**
 * Return if a field should be disabled according to the selected status state
 * @param {jQuery} $field Field to check
 * @returns {boolean}
 */
function isFieldDisabled($field) {
    const $form = $field.closest('tr');
    const $state = $form.find('[name="state"]');
    const disabledAttr = fieldsToDisabledAttr[$field.attr('name')];
    const $selectedState = $state.find(`option:selected`);
    if($selectedState.exists()) {
        return Boolean($selectedState.data(disabledAttr));
    }
    return true;
}

/**
 * Ensures only a specified number of checkboxes are checked for a given checkbox group
 * @param {jQuery} $container Global container of the array containing fields
 * @param {string} groupName Name of the checkbox group
 * @example ensureMaxCheckboxSelection($container, 'passStatusAtPurchaseOrderGeneration');
 * @returns {void}
 */
function ensureMaxCheckboxSelection($container, groupName) {
    const maxChecked = fieldsMaxChecked[groupName];
    if (maxChecked === undefined) {
        return;
    }
    const $checkboxes = $container.find(`[name="${groupName}"]`);
    $checkboxes
        .off(`change.checkboxesChange`)
        .on(`change.checkboxesChange`, function () {
            updateCheckboxes(groupName, $checkboxes);
        });
}

/**
 * Update checkboxes based on the number of checkboxes checked
 * @param groupName - Name of the checkbox group
 * @param $checkboxes - jQuery object containing the checkboxes to update
 * @example updateCheckboxes('passStatusAtPurchaseOrderGeneration', $checboxes);
 * @returns {void}
 */
function updateCheckboxes(groupName, $checkboxes) {
    const checkedCount = $checkboxes.filter(':checked').length;
    const maxChecked = fieldsMaxChecked[groupName];
    // Disable checkboxes if the maximum number of checkboxes are checked without the current one

    $checkboxes = $checkboxes.filter(':not(:checked)');
    $checkboxes.each(function() {
        const $checkbox = $(this);
        const isDisabled = isFieldDisabled($checkbox) || (maxChecked !== undefined && checkedCount >= maxChecked);

        $checkbox.prop('disabled', isDisabled);
        if (isDisabled) {
            $checkbox.prop('checked', false);
        }
    })
}
