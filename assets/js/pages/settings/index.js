import '../../../scss/pages/settings.scss';
import EditableDatatable, {MODE_ADD_ONLY, MODE_DOUBLE_CLICK, MODE_MANUAL, MODE_NO_EDIT, SAVE_MANUALLY, STATE_VIEWING, MODE_EDIT, MODE_EDIT_AND_ADD, } from "../../editatable";
import Flash, {INFO} from '../../flash';
import {initializeImports} from "./data/imports.js";
import {LOADING_CLASS} from "../../loading";

const index = JSON.parse($(`input#settings`).val());
let category = $(`input#category`).val();
let menu = $(`input#menu`).val();
let submenu = $(`input#submenu`).val();

let currentForm = null;
const forms = {};

//keys are from url with / replaced by _
//http://wiistock/parametrage/afficher/stock/receptions/champs_fixes_receptions => stock_receptions_champs_fixes_receptions
const initializers = {
    global_heures_travaillees: initializeWorkingHours,
    global_jours_non_travailles: initializeOffDays,
    global_apparence_site: initializeSiteAppearance,
    global_etiquettes: initializeGlobalLabels,
    stock_articles_etiquettes: initializeStockArticlesLabels,
    stock_articles_types_champs_libres: initializeStockArticlesTypesFreeFields,
    donnees_imports: initializeImports,
    stock_receptions_champs_fixes_receptions: initializeReceptionFixedFields,
    trace_acheminements_champs_fixes: initializeDispatchFixedFields,
    trace_arrivages_champs_fixes: initializeArrivalFixedFields,
    trace_services_champs_fixes: initializeHandlingFixedFields,
    stock_inventaires_frequences: initializeFrequencesTable,
    stock_inventaires_categories: initializeCategoriesTable,
    stock_receptions_types_litiges : initializeTypesLitige,
};

const slowOperations = [
    `FONT_FAMILY`,
    `MAX_SESSION_TIME`,
];

const $saveButton = $(`.save-settings`);

$(document).ready(() => {
    let canEdit = $(`input#edit`).val();

    updateMenu(submenu || menu, canEdit);

    $(`.settings-item`).on(`click`, function() {
        const selectedMenu = $(this).data(`menu`);

        $(`.settings-item.selected`).removeClass(`selected`);
        $(this).addClass(`selected`);

        updateMenu(selectedMenu, canEdit);
    });

    $saveButton.on(`click`, async function() {
        const form = forms[currentForm];
        const tablesToReload = [];
        let data = Form.process(form.element, {
            ignored: `[data-table-processing]`,
        });

        let hasErrors = false;

        if(data) {
            const tables = {};
            form.element.find(`[id][data-table-processing]`).each(function() {
                const datatable = EditableDatatable.of(this);
                const tableData = datatable.data();
                tables[$(this).data(`table-processing`)] = tableData;
                tablesToReload.push(datatable);
                hasErrors = Object.keys(tableData)
                    .filter((rowKey) => !tableData[rowKey])
                    .length > 0;
            });

            if(Object.entries(tables).length) {
                data.append(`datatables`, JSON.stringify(tables));
            }
        }

        if (!data || hasErrors) {
            return;
        }

        if ($saveButton.hasClass(LOADING_CLASS)) {
            Flash.add(INFO, `L'opération est en cours de traitement`);
            return;
        }

        const slow = Object.keys(data.asObject()).find(function(n) {
            return slowOperations.indexOf(n) !== -1;
        });

        if(slow || $saveButton.hasClass(LOADING_CLASS)) {
            Flash.add(`info`, `Mise à jour des paramétrage en cours, cette opération peut prendre quelques minutes`, false);
        }

        $saveButton.pushLoader('white');
        await AJAX.route(`POST`, `settings_save`)
            .json(data)
            .then(() => {
                for(const table of tablesToReload) {
                    if(table.mode !== MODE_EDIT) {
                        table.toggleEdit(STATE_VIEWING, true);
                    }
                }
                $saveButton.popLoader();
            })
            .catch(() => {
                $saveButton.popLoader();
            });
    });

    $(document).on(`click`, `.submit-field-param`, function() {
        const $button = $(this);
        const $modal = $button.closest(`.modal`);

        const data = Form.process($modal);
        const field = $modal.find(`[name=field]`).val();
        if(data) {
            AJAX.route(`POST`, `settings_save_field_param`, {field}).json(data);
            $modal.modal(`hide`);
        }
    });
});

function getCategoryLabel() {
    return index[category].label;
}

function getMenuLabel() {
    const menuData = index[category].menus[menu];

    if(typeof menuData === `string`) {
        return menuData;
    } else {
        return menuData.label;
    }
}

function getSubmenuLabel() {
    if(!submenu) {
        return null;
    } else {
        return index[category].menus[menu].menus[submenu].label;
    }
}

function updateMenu(selectedMenu, canEdit) {
    $(`.settings main > .settings-content`).addClass(`d-none`);

    const $selectedMenu = $(`.settings main > .settings-content[data-menu="${selectedMenu}"]`);
    $selectedMenu.removeClass(`d-none`);

    const displaySaveButton = $selectedMenu.data('saveButton');
    $saveButton.toggleClass('d-none', !displaySaveButton);

    let title;
    if(!submenu) {
        menu = selectedMenu;
        title = `${getCategoryLabel()} | <span class="bold">${getMenuLabel()}</span>`;
    } else {
        submenu = selectedMenu;
        title = `${getCategoryLabel()} | ${getMenuLabel()} | <span class="bold">${getSubmenuLabel()}</span>`;
    }

    const path = `${category}_${menu}` + (submenu ? `_` + submenu : ``);
    const $element = $(`[data-path="${path}"]`);

    if(!forms[path]) {
        currentForm = path;
        forms[path] = {
            element: $element,
            ...(initializers[path] ? initializers[path]($element, canEdit) : []),
        };

        console.log(initializers[path] ? `Initializiing ${path}` : `No initializer for ${path}`);
    }

    const $pageTitle = $(`#page-title`);
    $pageTitle.html(title);
    const textTitle = $pageTitle.text();
    document.title = `Paramétrage | ${textTitle}`;

    history.pushState({}, title, Routing.generate(`settings_item`, {
        category,
        menu,
        submenu,
    }));
}

function createManagementPage($container, config) {
    let selectedEntity = $container.find(`[name=entity]:first`).attr(`value`);

    const $table = $container.find(`.subentities-table`);
    const $editButton = $container.find(`.edit-button`);

    $saveButton.addClass('d-none');
    $editButton.removeClass('d-none');
    $table.attr(`id`, `table-${Math.floor(Math.random() * 1000000)}`);

    loadItems($container, config, selectedEntity, false);

    const table = EditableDatatable.create(`#${$table.attr(`id`)}`, {
        name: config.name,
        mode: config.edit ? MODE_MANUAL : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        route: config.table.route(selectedEntity),
        deleteRoute: config.table.deleteRoute,
        form: config.table.form,
        columns: config.table.columns,
        initComplete: () => console.warn('huh'),
        onEditStart: () => {
            $editButton.addClass('d-none');
            $saveButton.removeClass('d-none');

            loadItems($container, config, selectedEntity, true);
        },
        onEditStop: () => {
            $saveButton.addClass('d-none');
            $editButton.removeClass('d-none');
            loadItems($container, config, selectedEntity, false)
        },
    });

    $container.find(`[name=entity]`).on(`change`, function() {
        selectedEntity = $(this).val();

        loadItems($container, config, selectedEntity, table.state !== STATE_VIEWING);
        table.setURL(config.table.route(selectedEntity))
    });

    $editButton.on(`click`, function() {
        table.toggleEdit(undefined, true);
    });
}

function loadItems($container, config, type, edit) {
    const route = config.header.route(type, edit);

    $.post(route, function(data) {
        if(data.success) {
            const $itemContainer = $container.find(`.main-entity-content`);
            $itemContainer.empty();

            for(const item of data.data) {
                const value = item.value === undefined || item.value === null ? '' : item.value;
                $itemContainer.append(`
                    <div class="col-auto ml-3">
                        <div class="d-flex justify-content-center align-items-center py-2">
                            ${item.icon ? `<img src="/svg/reference_article/${item.icon}.svg" alt="Icône" width="20px">` : ``}
                            <div class="d-grid">
                                <span class="wii-field-name">${item.label}</span>
                                <span class="wii-body-text">${value}</span>
                            </div>
                        </div>
                    </div>
                `);
            }
        }
    });
}

function initializeWorkingHours($container, canEdit) {
    $saveButton.addClass('d-none');

    const table = EditableDatatable.create(`#table-working-hours`, {
        route: Routing.generate('settings_working_hours_api', true),
        mode: canEdit ? MODE_DOUBLE_CLICK : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        onEditStart: () => $saveButton.addClass('d-none'),
        onEditStop: () => $saveButton.removeClass('d-none'),
        columns: [
            {data: `day`, title: `Jour`},
            {data: `hours`, title: `Horaires de travail`},
            {data: `worked`, title: `Travaillé`},
        ],
    });
}

function initializeOffDays($container, canEdit) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);

    $saveButton.addClass('d-none');

    const table = EditableDatatable.create(`#table-off-days`, {
        route: Routing.generate(`settings_off_days_api`, true),
        deleteRoute: `settings_off_days_delete`,
        mode: canEdit ? MODE_ADD_ONLY : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: true,
        paginate: true,
        onInit: () => {
            $addButton.removeClass(`d-none`);
        },
        onEditStart: () => {
            $saveButton.removeClass('d-none');
            $tableHeader.addClass('d-none');
        },
        onEditStop: () => {
            $saveButton.removeClass('d-none');
            $tableHeader.removeClass('d-none');
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `day`, title: `Jour`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            day: `<input type="date" name="day" class="form-control data" data-global-error="Jour" required/>`,
        },
        columnDefs: [
            {targets: 1, width: '100%'},
        ],
    });

    $addButton.on(`click`, function() {
        table.addRow();
    });
}

function initializeSiteAppearance() {
    $('#upload-website-logo').on('change', () => updateImagePreview('#preview-website-logo', '#upload-website-logo'));
    $('#upload-email-logo').on('change', () => updateImagePreview('#preview-email-logo', '#upload-email-logo'));
    $('#upload-mobile-logo-login').on('change', () => updateImagePreview('#preview-mobile-logo-login', '#upload-mobile-logo-login'));
    $('#upload-mobile-logo-header').on('change', () => updateImagePreview('#preview-mobile-logo-header', '#upload-mobile-logo-header'));
}

function initializeGlobalLabels() {
    $('#upload-label-logo').on('change', () => updateImagePreview('#preview-label-logo', '#upload-label-logo'));
}

function initializeStockArticlesLabels() {
    $(`#show-destination-in-label`).on(`change`, function() {
        if($(this).prop(`checked`)) {
            $('#show-dropzone-in-label').prop('checked', false);
        }
    });

    $(`#show-dropzone-in-label`).on(`change`, function() {
        if($(this).prop(`checked`)) {
            $('#show-destination-in-label').prop('checked', false);
        }
    });
}

function initializeStockArticlesTypesFreeFields($container, canEdit) {
    const booleanDefaultValue = JSON.parse($(`#article-free-field-boolean-default-value`).val());

    createManagementPage($container, {
        name: `articlesFreeFields`,
        edit: canEdit,
        header: {
            route: (type, edit) => Routing.generate('settings_free_field_header', {type, edit}, true),
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
                actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
                label: `<input type="text" name="label" class="form-control data" data-global-error="Libellé"/>`,
                type: `
                    <select class="form-control data" name="type">
                        <option selected disabled>Type de champ</option>
                        <option value="text">Texte</option>
                        <option value="number">Nombre</option>
                        <option value="booleen">Oui/Non</option>
                        <option value="date">Date</option>
                        <option value="datetime">Date et heure</option>
                        <option value="list">Liste</option>
                        <option value="list multiple">Liste multiple</option>
                    </select>`,
                appliesTo: JSON.parse($(`#article-free-field-categories`).val()),
                elements: `<input type="text" name="elements" class="form-control data" data-global-error="Eléments"/>`,
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
            },
        }
    });

    $container.on(`change`, `[name=type]`, function() {
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
        }

        if(selectors[type]) {
            $row.find(selectors[type]).removeClass(`d-none`);
        }

        $row.find(`[name=elements]`)
            .toggleClass(`d-none`, !isList)
            .trigger(`keyup`);
    });

    $container.on(`keyup`, `[name=elements]`, function() {
        const $input = $(this);
        const $row = $input.closest(`tr`);
        const $defaultValue = $row.find(`select[name="defaultValue"]`);
        const elements = $input.val().split(`;`);

        $defaultValue.empty();
        for(const element of elements) {
            $defaultValue.append(new Option(element, element, false, false))
        }

        $defaultValue.trigger('change');
    });
}

function initializeReceptionFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-reception-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `réception`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paginate: false,
        onEditStart: () => $saveButton.show(),
        onEditStop: () => $saveButton.hide(),
        columns: [
            {data: `label`, title: `Champ fixe`},
            {data: `displayedCreate`, title: `Afficher`},
            {data: `requiredCreate`, title: `Obligatoire`},
            {data: `displayedEdit`, title: `Afficher`},
            {data: `requiredEdit`, title: `Obligatoire`},
            {data: `displayedFilters`, title: `Afficher`},
        ],
    });
}

function initializeDispatchFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-dispatch-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `acheminements`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paginate: false,
        onEditStart: () => $saveButton.show(),
        onEditStop: () => $saveButton.hide(),
        columns: [
            {data: `label`, title: `Champ fixe`},
            {data: `displayedCreate`, title: `Afficher`},
            {data: `requiredCreate`, title: `Obligatoire`},
            {data: `displayedEdit`, title: `Afficher`},
            {data: `requiredEdit`, title: `Obligatoire`},
            {data: `displayedFilters`, title: `Afficher`},
        ],
    });
}

function initializeArrivalFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-arrival-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `arrivage`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paginate: false,
        onEditStart: () => $saveButton.show(),
        onEditStop: () => $saveButton.hide(),
        columns: [
            {data: `label`, title: `Champ fixe`},
            {data: `displayedCreate`, title: `Afficher`},
            {data: `requiredCreate`, title: `Obligatoire`},
            {data: `displayedEdit`, title: `Afficher`},
            {data: `requiredEdit`, title: `Obligatoire`},
            {data: `displayedFilters`, title: `Afficher`},
        ],
    });
}

function initializeHandlingFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-handling-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `services`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paginate: false,
        onEditStart: () => $saveButton.show(),
        onEditStop: () => $saveButton.hide(),
        columns: [
            {data: `label`, title: `Champ fixe`},
            {data: `displayedCreate`, title: `Afficher`},
            {data: `requiredCreate`, title: `Obligatoire`},
            {data: `displayedEdit`, title: `Afficher`},
            {data: `requiredEdit`, title: `Obligatoire`},
            {data: `displayedFilters`, title: `Afficher`},
        ],
    });
}

function initializeFrequencesTable(){
    $saveButton.addClass('d-none');

    const table = EditableDatatable.create(`#frequencesTable`, {
        route: Routing.generate('frequencies_api', true),
        deleteRoute: `settings_delete_frequency`,
        mode: MODE_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $saveButton.removeClass('d-none');
        },
        onEditStop: () => {
            $saveButton.addClass('d-none');
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libellé`},
            {data: `nb_months`, title: `Nombre de mois`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed' data-global-error="Libellé"/>`,
            nb_months: `<input type='number' name='nbMonths' min='1' class='data form-control needed' data-global-error="Nombre de mois"/>`,
        },
    });
}

function initializeCategoriesTable(){
    $saveButton.addClass('d-none');
    const $frequencyOptions = JSON.parse($(`#frequency_options`).val());

    const table = EditableDatatable.create(`#categoriesTable`, {
        route: Routing.generate('categories_api', true),
        deleteRoute: `settings_delete_category`,
        mode: MODE_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $saveButton.removeClass('d-none');
        },
        onEditStop: () => {
            $saveButton.addClass('d-none');
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libellé`},
            {data: `frequency`, title: `Fréquence`},
            {data: `permanent`, title: `Permanent`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed'  data-global-error="Libellé"/>`,
            frequency: `<select name='frequency' class='form-control data needed' data-global-error="Fréquence>`+$frequencyOptions+`</select>`,
            permanent: `<div class='checkbox-container'><input type='checkbox' name='permanent' class='form-control data'/></div>`,
        },
    });
}

function initializeTypesLitige(){
    $saveButton.addClass('d-none');
    const table = EditableDatatable.create(`#table-types-litige`, {
        route: Routing.generate('types_litige_api', true),
        deleteRoute: `settings_delete_type_litige`,
        mode: MODE_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $saveButton.removeClass('d-none');
        },
        onEditStop: () => {
            $saveButton.addClass('d-none');
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libellé`},
            {data: `description`, title: `Description`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed' data-global-error='Libellé'/>`,
            description: `<input type='text' name='description' class='form-control data' data-global-error='Description'/>`,
        },
    });
}


