import {createManagementPage} from './utils';

const $saveButton = $(`.save-settings`);

function generateFreeFieldForm() {
    const booleanDefaultValue = JSON.parse($(`[name=default-value-template]`).val());

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
        elements: `<input type="text" name="elements" required class="form-control data" data-global-error="Eléments"/>`,
        defaultValue: `
            <div class="d-none boolean-default-value">${booleanDefaultValue}</div>
            <input type="text" name="defaultValue" class="form-control data" data-global-error="Valeur par défaut"/>
            <input type="number" name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"/>
            <input type="date" name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"/>
            <input type="datetime-local" name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"/>
            <select name="defaultValue" class="form-control data d-none" data-global-error="Valeur par défaut"></select>
        `,
        displayedCreate: `<input type="checkbox" name="displayedCreate" class="form-control data" data-global-error="Affiché à la création"/>`,
        requiredCreate: `<input type="checkbox" name="requiredCreate" class="form-control data" data-global-error="Obligatoire à la création"/>`,
        requiredEdit: `<input type="checkbox" name="requiredEdit" class="form-control data" data-global-error="Obligatoire à la modification"/>`,
    };
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
    for(const element of elements) {
        $defaultValue.append(new Option(element, element, false, false));
    }

    $defaultValue.trigger('change');
}

export function initializeStockArticlesTypesFreeFields($container, canEdit) {
    createManagementPage($container, {
        name: `freeFields`,
        edit: canEdit,
        header: {
            route: (type, edit) => Routing.generate('settings_type_header', {type, edit}, true),
        },
        table: {
            route: (type) => Routing.generate('settings_free_field_api', {type}, true),
            deleteRoute: `settings_free_field_delete`,
            columns: [
                {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
                {data: `label`, title: `Libellé`},
                {data: `appliesTo`, title: `S'applique à`},
                {data: `type`, title: `Typage`},
                {data: `elements`, title: `Éléments`},
                {data: `defaultValue`, title: `Valeur par défaut`},
                {data: `displayedCreate`, title: `<div class='small-column'>Affiché à la création</div>`},
                {data: `requiredCreate`, title: `<div class='small-column'>Obligatoire à la création</div>`},
                {data: `requiredEdit`, title: `<div class='small-column'>Obligatoire à la modification</div>`},
            ],
            form: {
                appliesTo: JSON.parse($(`#article-free-field-categories`).val()),
                ...generateFreeFieldForm(),
            },
        },
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
}

export function initializeStockDeliveryTypesFreeFields($container, canEdit) {
    createManagementPage($container, {
        name: `freeFields`,
        edit: canEdit,
        header: {
            route: (type, edit) => Routing.generate('settings_type_header', {type, edit}, true),
        },
        table: {
            route: (type) => Routing.generate('settings_free_field_api', {type}, true),
            deleteRoute: `settings_free_field_delete`,
            columns: [
                {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
                {data: `label`, title: `Libellé`},
                {data: `type`, title: `Typage`},
                {data: `elements`, title: `Éléments`},
                {data: `defaultValue`, title: `Valeur par défaut`},
                {data: `displayedCreate`, title: `<div class='small-column'>Affiché à la création</div>`},
                {data: `requiredCreate`, title: `<div class='small-column'>Obligatoire à la création</div>`},
                {data: `requiredEdit`, title: `<div class='small-column'>Obligatoire à la modification</div>`},
            ],
            form: generateFreeFieldForm(),
        },
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
}

export function initializeStockCollectTypesFreeFields($container, canEdit) {
    createManagementPage($container, {
        name: `freeFields`,
        edit: canEdit,
        header: {
            route: (type, edit) => Routing.generate('settings_type_header', {type, edit}, true),
        },
        table: {
            route: (type) => Routing.generate('settings_free_field_api', {type}, true),
            deleteRoute: `settings_free_field_delete`,
            columns: [
                {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
                {data: `label`, title: `Libellé`},
                {data: `type`, title: `Typage`},
                {data: `elements`, title: `Éléments`},
                {data: `defaultValue`, title: `Valeur par défaut`},
                {data: `displayedCreate`, title: `<div class='small-column'>Affiché à la création</div>`},
                {data: `requiredCreate`, title: `<div class='small-column'>Obligatoire à la création</div>`},
                {data: `requiredEdit`, title: `<div class='small-column'>Obligatoire à la modification</div>`},
            ],
            form: generateFreeFieldForm(),
        },
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
}

export function initializeTrackingDispatchTypesFreeFields($container, canEdit) {
    createManagementPage($container, {
        name: `freeFields`,
        edit: canEdit,
        header: {
            route: (type, edit) => Routing.generate('settings_type_header', {type, edit}, true),
        },
        table: {
            route: (type) => Routing.generate('settings_free_field_api', {type}, true),
            deleteRoute: `settings_free_field_delete`,
            columns: [
                {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
                {data: `label`, title: `Libellé`},
                {data: `type`, title: `Typage`},
                {data: `elements`, title: `Éléments`},
                {data: `defaultValue`, title: `Valeur par défaut`},
                {data: `displayedCreate`, title: `<div class='small-column'>Affiché à la création</div>`},
                {data: `requiredCreate`, title: `<div class='small-column'>Obligatoire à la création</div>`},
                {data: `requiredEdit`, title: `<div class='small-column'>Obligatoire à la modification</div>`},
            ],
            form: generateFreeFieldForm(),
        },
    });

    $container.on(`change`, `[name=type]`, defaultValueTypeChange);
    $container.on(`keyup`, `[name=elements]`, onElementsChange);
    $container.on(`change`, `[name=pushNotifications]`, function() {
        const $radio = $(this);

        $container.find(`[name=notificationEmergencies]`).closest(`.col-auto`).toggleClass(`d-none`, $radio.val());
    })
}
