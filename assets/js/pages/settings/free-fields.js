import {createManagementPage} from './utils';
import EditableDatatable, {MODE_CLICK_EDIT, MODE_CLICK_EDIT_AND_ADD, MODE_NO_EDIT, SAVE_MANUALLY} from "../../editatable";

const $saveButton = $(`.save-settings`);
const $discardButton = $(`.discard-settings`);

function generateFreeFieldForm() {
    return {
        actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
        label: `<input type="text" name="label" required class="form-control data" data-global-error="Libellé"/>`,
        type: `
            <select class="form-control data" name="type" required>
                <option selected disabled>Type de champ</option>
                <option value="text">Texte</option>
                <option value="number">Nombre</option>
                <option value="booleen">Oui/Non</option>
                <option value="date">Date</option>
                <option value="datetime">Date et heure</option>
                <option value="list">Liste</option>
                <option value="list multiple">Liste multiple</option>
            </select>`,
        elements: `<input type="text" name="elements" required class="form-control data d-none" data-global-error="Eléments"/>`,
        defaultValue: () => {
            // executed each time we add a new row to calculate new id
            const $booleanDefaultValue = $(`<div class="wii-switch-small">${JSON.parse($(`[name=default-value-template]`).val())}</div>`);
            $booleanDefaultValue.find(`[type=radio]`).each(function() {
                const $input = $(this);
                const $label = $booleanDefaultValue.find(`[for=${$input.attr(`id`)}]`);

                const id = `defaultValue-${Math.floor(Math.random() * 1000000)}`;
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
        displayedCreate: `<input type="checkbox" name="displayedCreate" class="form-control data" data-global-error="Affiché à la création"/>`,
        requiredCreate: `<input type="checkbox" name="requiredCreate" class="form-control data" data-global-error="Obligatoire à la création"/>`,
        requiredEdit: `<input type="checkbox" name="requiredEdit" class="form-control data" data-global-error="Obligatoire à la modification"/>`,
    };
}

function generateFreeFieldColumns(canEdit = true, appliesTo = false) {
    return [
        ...(canEdit ? [{data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false, width: `2%`}] : []),
        {data: `label`, title: `Libellé`, required: true},
        ...(appliesTo ? [{data: `appliesTo`, title: `S'applique à`}] : []),
        {data: `type`, title: `Typage`, required: true},
        {data: `elements`, title: `Éléments<br><div class='wii-small-text'>(Séparés par des ';')</div>`, className: `no-interaction`},
        {data: `defaultValue`, title: `Valeur par défaut`},
        {data: `displayedCreate`, title: `<div class='small-column'>Affiché à la création</div>`, width: `8%`},
        {data: `requiredCreate`, title: `<div class='small-column'>Obligatoire à la création</div>`, width: `8%`},
        {data: `requiredEdit`, title: `<div class='small-column'>Obligatoire à la modification</div>`, width: `8%`},
    ];
}

function defaultValueTypeChange() {
    const $select = $(this);
    const $row = $select.closest(`tr`);
    const type = $select.val();

    $row.find(`[name=defaultValue], .boolean-default-value`).addClass(`d-none`);

    const isList = [`list`, `list multiple`].includes(type);
    const selectors = {
        "text": `[name=defaultValue][type=text]`,
        "number": `[name=defaultValue][type=number]`,
        "date": `[name=defaultValue][type=date]`,
        "datetime": `[name=defaultValue][type=datetime-local]`,
        "booleen": `.boolean-default-value`,
        "list": `select[name=defaultValue]`,
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

export function createFreeFieldsPage($container, canEdit) {
    const category = $container.find('.management-body').data('category');
    createManagementPage($container, {
        name: `freeFields`,
        mode: canEdit ? MODE_CLICK_EDIT_AND_ADD : MODE_NO_EDIT,
        newTitle: 'Ajouter un type et des champs libres',
        header: {
            route: (type, edit) => Routing.generate('settings_type_header', {type, edit, category}, true),
            delete: {
                checkRoute: 'settings_types_check_delete',
                selectedEntityLabel: 'type',
                route: 'settings_types_delete',
                modalTitle: 'Supprimer le type',
            },
        },
        table: {
            route: (type) => Routing.generate('settings_free_field_api', {type}, true),
            deleteRoute: `settings_free_field_delete`,
            columns: generateFreeFieldColumns(canEdit),
            form: generateFreeFieldForm(),
        },
        onEditStop: () => {
            updateCheckedType($container);
        }
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
    $container.on(`change`, `[type=radio][name=pushNotifications]`, function() {
        const $radio = $(this);
        const val = Number($radio.val());
        $container.find(`[name=notificationEmergencies]`)
            .closest(`.main-entity-content-item`)
            .toggleClass(`d-none`, val !== 2);
    });
}

export function initializeStockArticlesTypesFreeFields($container, canEdit) {
    const category = $container.find('.management-body').data('category');
    createManagementPage($container, {
        name: `freeFields`,
        mode: canEdit ? MODE_CLICK_EDIT_AND_ADD : MODE_NO_EDIT,
        newTitle: 'Ajouter un type et des champs libres',
        header: {
            route: (type, edit) => Routing.generate('settings_type_header', {type, edit, category}, true),
            delete: {
                checkRoute: 'settings_types_check_delete',
                selectedEntityLabel: 'type',
                route: 'settings_types_delete',
                modalTitle: 'Supprimer le type',
            },
        },
        table: {
            route: (type) => Routing.generate('settings_free_field_api', {type}, true),
            deleteRoute: `settings_free_field_delete`,
            columns: generateFreeFieldColumns(canEdit, true),
            form: {
                appliesTo: JSON.parse($(`#article-free-field-categories`).val()),
                ...generateFreeFieldForm(),
            },
        },
        onEditStop: () => {
            $container.find(`[type=radio]:checked + label`).text($container.find(`[name="label"]`).val());
        }
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
}

export function initializeTraceMovementsFreeFields($container, canEdit) {
    $saveButton.addClass('d-none');

    const table = EditableDatatable.create(`#table-movement-free-fields`, {
        route: Routing.generate(`settings_free_field_api`, {
            type: $(`#table-movement-free-fields`).data(`type`),
        }),
        deleteRoute: `settings_free_field_delete`,
        mode: canEdit ? MODE_CLICK_EDIT_AND_ADD : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: true,
        paging: true,
        onEditStart: () => {
            $saveButton.removeClass('d-none');
            $discardButton.removeClass('d-none');
        },
        onEditStop: () => {
            $saveButton.removeClass('d-none');
            $discardButton.removeClass('d-none');
        },
        columns: generateFreeFieldColumns(canEdit),
        form: generateFreeFieldForm(),
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
}

export function initializeReceptionsFreeFields($container, canEdit) {
    $saveButton.addClass('d-none');

    const table = EditableDatatable.create(`#table-reception-free-fields`, {
        route: Routing.generate(`settings_free_field_api`, {
            type: $(`#table-reception-free-fields`).data(`type`),
        }),
        deleteRoute: `settings_free_field_delete`,
        mode: canEdit ? MODE_CLICK_EDIT_AND_ADD : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: true,
        paging: true,
        onEditStart: () => {
            $saveButton.removeClass('d-none');
            $discardButton.removeClass('d-none');
        },
        onEditStop: () => {
            $saveButton.removeClass('d-none');
            $discardButton.removeClass('d-none');
        },
        columns: generateFreeFieldColumns(canEdit),
        form: generateFreeFieldForm(),
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
}

export function initializeIotFreeFields($container, canEdit) {
    createManagementPage($container, {
        name: `freeFields`,
        mode: canEdit ? MODE_CLICK_EDIT_AND_ADD : MODE_NO_EDIT,
        newTitle: 'Ajouter un type et des champs libres',
        table: {
            route: (type) => Routing.generate('settings_free_field_api', {type}, true),
            deleteRoute: `settings_free_field_delete`,
            columns: generateFreeFieldColumns(canEdit),
            form: {
                ...generateFreeFieldForm(),
            },
        },
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
}

function updateCheckedType($container) {
    const $radio = $container.find(`[type=radio]:checked + label`);
    const $radioWrapper = $('<span />', {
        text: $container.find(`[name="label"]`).val(),
        class: 'd-inline-flex align-items-center'
    });
    $radio.html($radioWrapper);

    $radioWrapper.text($container.find(`[name="label"]`).val());
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
