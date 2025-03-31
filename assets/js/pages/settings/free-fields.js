import {createManagementPage} from './utils';
import EditableDatatable, {MODE_CLICK_EDIT_AND_ADD, MODE_NO_EDIT, SAVE_MANUALLY} from "@app/editatable";

import Form from '@app/form';
import AJAX, {GET, POST} from "@app/ajax";
import Routing from '@app/fos-routing';
import {generateRandomNumber} from "@app/utils";

const MODE_ARRIVAL = `arrival`;
const MODE_TRACKING = `tracking`;
const MODE_TRACKING_MOVEMENT = `tracking_movement`;
const MODE_DISPATCH = `dispatch`;
const MODE_HANDLING = `handling`;
const MODE_ARTICLE = `article`
const MODE_DELIVERY_REQUEST = `delivery_request`;
const MODE_PRODUCTION = `production`;
const MODE_RECEPTION = `reception`;

const TEXT_TYPING = `text`;
const NUMBER_TYPING = `number`;
const BOOLEAN_TYPING = `booleen`;
const DATE_TYPING = `date`;
const DATETIME_TYPING = `datetime`;
const LIST_TYPING = `list`;
const MULTIPLE_LIST_TYPING = `list multiple`;

const TYPINGS = {
    [TEXT_TYPING]: `Texte`,
    [NUMBER_TYPING]: `Nombre`,
    [BOOLEAN_TYPING]: `Oui/Non`,
    [DATE_TYPING]: `Date`,
    [DATETIME_TYPING]: `Date et heure`,
    [LIST_TYPING]: `Liste`,
    [MULTIPLE_LIST_TYPING]: `Liste multiple`,
};

const $saveButton = $(`.save-settings`);
const $discardButton = $(`.discard-settings`);
const $managementButtons = $(`.save-settings, .discard-settings`);
let canTranslate = true;

function generateFreeFieldForm() {
    const fieldTypes = Object.entries(TYPINGS)
        .reduce((acc, [name, label]) => `${acc}<option value="${name}">${label}</option>`, ``);

    const freeFieldCategories = $(`#free-field-categories`).val()
    return {
        actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
        label: `<input type="text" name="label" required class="form-control data" data-global-error="Libellé"/>`,
        type: `
            <select class="form-control data" name="type" required>
                <option selected disabled>Type de champ</option>
                ${fieldTypes}
            </select>`,
        elements: `<input type="text" name="elements" required class="form-control data d-none" data-global-error="Eléments"/>`,
        minCharactersLength: `<input type="number" name="minCharactersLength" min="1" class="form-control data d-none" data-global-error="Nb caractères min"/>`,
        maxCharactersLength: `<input type="number" name="maxCharactersLength" min="1" class="form-control data d-none" data-global-error="Nb caractères max"/>`,
        appliesTo: freeFieldCategories ? JSON.parse(freeFieldCategories): undefined,
        defaultValue: () => {
            // executed each time we add a new row to calculate new id
            const $booleanDefaultValue = $(`<div class="wii-switch-small">${JSON.parse($(`[name=default-value-template]`).val())}</div>`);
            $booleanDefaultValue.find(`[type=radio]`).each(function() {
                const $input = $(this);
                const $label = $booleanDefaultValue.find(`[for=${$input.attr(`id`)}]`);

                const id = `defaultValue-${generateRandomNumber()}`;
                $input.attr(`id`, id);
                $label.attr(`for`, id);
            });

            return `
                <form class="d-none boolean-default-value">${$booleanDefaultValue.prop(`outerHTML`)}</form>
                <input type="text" name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"/>
                <input type="number" name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"/>
                <input type="date" name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"/>
                <input type="datetime-local" name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"/>
                <select name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"></select>
            `;
        },
    };
}

function generateFreeFieldManagementRuleForm(category) {
    return {
        actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
        freeField: `<label class="w-100"><select class="form-control data w-100 needed" required data-s2="freeField" data-min-length="0" data-other-params-category-ff="${category}" name="freeField"></select></label>`,
        displayedCreate: `<input type="checkbox" name="displayedCreate" class="form-control data" data-global-error="Affiché à la création"/>`,
        requiredCreate: `<input type="checkbox" name="requiredCreate" class="form-control data" data-global-error="Obligatoire à la création"/>`,
        displayedEdit: `<input type="checkbox" name="displayedEdit" class="form-control data" data-global-error="Affiché à la modification"/>`,
        requiredEdit: `<input type="checkbox" name="requiredEdit" class="form-control data" data-global-error="Obligatoire à la modification"/>`,
    };
}

function generateFreeFieldColumns(canEdit = true, appliesTo = false) {
    return [
        ...(canEdit ? [{data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false, width: `2%`}] : []),
        {data: `label`, title: `Libellé`, required: true, width: `5%`},
        ...(appliesTo ? [{data: `appliesTo`, title: `S'applique à`}] : []),
        {data: `type`, title: `Typage`, required: true, width: `7%`},
        {data: `elements`, title: `Éléments<br><div class='wii-small-text'>(Séparés par des ';')</div>`, className: `no-interaction`, width: `10%`},
        {data: `minCharactersLength`, title: `Nb caractères<br><div class='wii-small-text'>(Min)</div>`, width: `10%`},
        {data: `maxCharactersLength`, title: `Nb caractères<br><div class='wii-small-text'>(Max)</div>`, width: `10%`},
        {data: `defaultValue`, title: `<div class='small-column'>Valeur par défaut</div>`, width: `8%`},
    ];
}

function generateFreeFieldManagementRulesColumns(canEdit = true, appliesTo = false) {
    return [
        ...(canEdit ? [{data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false, width: `2%`}] : []),
        {data: `freeField`, title: `<div class='small-column'>Champ Libre</div>`, width: `8%`},
        {data: `displayedCreate`, title: `<div class='small-column'>Affiché à la création</div>`, width: `8%`},
        {data: `requiredCreate`, title: `<div class='small-column'>Obligatoire à la création</div>`, width: `8%`},
        {data: `displayedEdit`, title: `<div class='small-column'>Affiché à la modification</div>`, width: `8%`},
        {data: `requiredEdit`, title: `<div class='small-column'>Obligatoire à la modification</div>`, width: `8%`},
    ];
}

function onTypingChange($select) {
    defaultValueTypeChange($select);
    charactersLengthChange($select);
}

function charactersLengthChange($select) {
    const $row = $select.closest(`tr`);
    const $charactersLengthFields = $row.find(`[name=minCharactersLength], [name=maxCharactersLength]`);

    $charactersLengthFields.addClass(`d-none`);

    if($select.val() === TEXT_TYPING) {
        $charactersLengthFields.removeClass(`d-none`);
    }
}

function defaultValueTypeChange($select) {
    const $row = $select.closest(`tr`);
    const type = $select.val();

    $row.find(`[name=defaultValue], .boolean-default-value`).addClass(`d-none`);

    const isList = [`list`, `list multiple`].includes(type);
    const selectors = {
        [TEXT_TYPING]: `[name=defaultValue][type=text]`,
        [NUMBER_TYPING]: `[name=defaultValue][type=number]`,
        [DATE_TYPING]: `[name=defaultValue][type=date]`,
        [DATETIME_TYPING]: `[name=defaultValue][type=datetime-local]`,
        [BOOLEAN_TYPING]: `.boolean-default-value`,
        [LIST_TYPING]: `select[name=defaultValue]`,
    };

    if(selectors[type]) {
        $row.find(selectors[type]).removeClass(`d-none`);
    }

    $row.find(`[name=elements]`)
        .toggleClass(`d-none`, !isList)
        .trigger(`keyup`);
}

function onElementsChange() {
    const $input = $(this);
    const $row = $input.closest(`tr`);
    const $defaultValue = $row.find(`select[name="defaultValue"]`);
    const elements = $input.val().split(`;`);

    $defaultValue.empty();
    $defaultValue.append(new Option("", null, false, false));
    for(const element of elements) {
        $defaultValue.append(new Option(element, element, false, false));
    }

    $defaultValue.trigger('change');
}

export function createArrivalsFreeFieldsPage($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_ARRIVAL);
}

export function createDispatchFreeFieldsPage($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_DISPATCH);
}

export function createHandlingFreeFieldsPage($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_HANDLING);
}

export function createDeliveryRequestFieldsPage($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_DELIVERY_REQUEST);
}

export function createProductionFreeFieldsPage($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_PRODUCTION);
}

export function createFreeFieldsPage($container, canEdit, mode) {
    const category = $container.find('[name="category"]').val();
    const table = createManagementPage($container, {
        mode: canEdit ? MODE_CLICK_EDIT_AND_ADD : MODE_NO_EDIT,
        newTitle: 'Ajouter un type et des champs libres',
        category: category,
        header: {
            route: (type, edit) => Routing.generate('settings_type_header', {type, edit, category}, true),
            delete: {
                checkRoute: 'settings_types_check_delete',
                selectedEntityLabel: 'type',
                route: 'settings_types_delete',
                modalTitle: 'Supprimer le type',
            },
        },
        tableFreeFields: {
            name: `freeFields`,
            route: (category) => Routing.generate('settings_free_field_api', {category}),
            deleteRoute: `settings_free_field_delete`,
            columns: generateFreeFieldColumns(canEdit, mode === MODE_ARTICLE),
            form: generateFreeFieldForm(),
        },
        tableManagement: {
            name: `freeFieldManagementRules`,
            route: (type) => Routing.generate('settings_free_field_management_rule_api', {type}),
            deleteRoute: `settings_free_field_management_rule_delete`,
            columns: generateFreeFieldManagementRulesColumns(canEdit),
            form: generateFreeFieldManagementRuleForm(category),
        },
        ...createFreeFieldsListeners($container, canEdit, mode),
    });

    setupTranslationsModal($container, table);

    $container.on(`change`, `[name=type]`, function() {
        onTypingChange($(this));
    });
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
    $container.on(`change`, `[type=radio][name=pushNotifications]`, function() {
        const $radio = $(this);
        const enabledIfEmergencies = Number($radio.val()) === 2;

        const $notificationEmergencies = $container.find(`[name=notificationEmergencies]`);
        $notificationEmergencies
            .closest(`.main-entity-content-item`)
            .toggleClass(`d-none`, !enabledIfEmergencies);

        $notificationEmergencies.prop(`required`, enabledIfEmergencies);
    });
}

function setupTranslationsModal($container, table) {
    Form.create($container.find(".edit-translation-modal"))
        .onSubmit((data, form) => {
            form.loading(
                () => AJAX.route(POST, `settings_edit_type_translations`)
                    .json(data)
                    .then(response => {
                        if(response.success) {
                            $container.find(`.edit-translation-modal`).modal(`hide`);
                            table.table.ajax.reload();
                        }
                    })
            )
        });
}

function createFreeFieldsListeners($container, canEdit, mode) {
    const $addButton = $container.find(`.add-row-button`);
    const $translateButton = $container.find(`.translate-labels-button`);
    const $filtersContainer = $container.find('.filters-container');
    const $pageBody = $container.find('.page-body');
    const $addRow = $container.find(`.add-row`);
    const $translateLabels = $container.find('.translate-labels');
    const $modalEditTranslations = $container.find(".edit-translation-modal");

    if(mode) {
        return {
            onInit: () => {
                $addButton.removeClass(`d-none`);
            },
            onEditStart: () => {
                $managementButtons.removeClass('d-none');
                if(mode) {
                    $addRow.addClass('d-none');
                    if (canTranslate) {
                        $translateLabels.removeClass('d-none');
                    }

                    $translateButton
                        .off('click.freeFieldsTranslation')
                        .on('click.freeFieldsTranslation', function () {
                            const params = {
                                type: $container.find('[name=entity]:checked').val(),
                            };
                            wrapLoadingOnActionButton($(this), () => (
                                AJAX.route(GET, `settings_edit_type_translations_api`, params)
                                    .json()
                                    .then(response => {
                                        $modalEditTranslations.find(`.modal-body`).html(response.html);
                                        $modalEditTranslations.modal('show');
                                    })
                            ));
                        });
                }
            },
            onEditStop: () => {
                if(mode !== MODE_TRACKING) {
                    updateCheckedType($container);
                }

                $managementButtons.addClass('d-none');
                $addRow.removeClass('d-none');
                $filtersContainer.removeClass('d-none');
                if (canTranslate) {
                    $translateLabels.addClass('d-none');
                }
                canTranslate = true;
                $pageBody.find('.wii-title').remove();
            },
        };
    } else {
        return {
            onEditStop: () => {
                updateCheckedType($container);
            },
        }
    }
}

export function initializeStockArticlesTypesFreeFields($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_ARTICLE);
}

export function initializeTraceMovementsFreeFields($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_TRACKING_MOVEMENT);
}

export function initializeReceptionsFreeFields($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_RECEPTION);
}

export function initializeIotFreeFields($container, canEdit) {
    createFreeFieldsPage($container, canEdit, MODE_RECEPTION);
}
function updateCheckedType($container) {
    const $typeIdHidden =  $container.find("[name='typeId']");
    if (!$typeIdHidden.val()) {
        window.location.reload();
    }

    const $radio = $container.find(`[type=radio]:checked + label`);
    const $radioWrapper = $('<span />', {
        text: $container.find(`[name="label"]`).val(),
        class: 'd-inline-flex align-items-center'
    });
    $radio.html($radioWrapper);
    const label = `<div class="mr-2 dt-type-color" style="background: ${$container.find(`[name="color"]`).val()}"></div>${$container.find(`[name="label"]`).val()}`
    $radioWrapper.html(label);
    const $logo = $container.find(`[name="logo"]`);
    const $logoPreview = $logo.siblings('.preview-container').find('.image');
    if ($logo.exists() && $logoPreview.exists() && $logoPreview.attr('src')) {
        const $clonedPreview = $logoPreview.clone();
        $clonedPreview
            .attr('id', null)
            .attr('height', null)
            .attr('width', '15px')
            .attr('class', 'mr-2')
        $radioWrapper.prepend($clonedPreview);
    }
}
