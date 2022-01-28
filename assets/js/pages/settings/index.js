import '../../../scss/pages/settings.scss';
import EditableDatatable, {MODE_ADD_ONLY, MODE_DOUBLE_CLICK, MODE_NO_EDIT, SAVE_MANUALLY, STATE_VIEWING, MODE_EDIT, MODE_EDIT_AND_ADD, } from "../../editatable";
import Flash, {INFO} from '../../flash';
import {LOADING_CLASS} from "../../loading";
import {initUserPage} from "./users/users";
import {initializeImports} from "./data/imports.js";
import {initializeStockArticlesTypesFreeFields, createFreeFieldsPage, initializeStockMovementsFreeFields,} from "./free-fields";

const index = JSON.parse($(`input#settings`).val());
let category = $(`input#category`).val();
let menu = $(`input#menu`).val();
let submenu = $(`input#submenu`).val();

let currentForm = null;
const forms = {};

let editing = false;

//keys are from url with / replaced by _
//http://wiistock/parametrage/afficher/stock/receptions/champs_fixes_receptions => stock_receptions_champs_fixes_receptions
const initializers = {
    global_heures_travaillees: initializeWorkingHours,
    global_jours_non_travailles: initializeOffDays,
    global_apparence_site: initializeSiteAppearance,
    global_etiquettes: initializeGlobalLabels,
    stock_articles_etiquettes: initializeStockArticlesLabels,
    stock_articles_types_champs_libres: initializeStockArticlesTypesFreeFields,
    stock_demandes_types_champs_libres_livraisons: createFreeFieldsPage,
    stock_demandes_types_champs_libres_collectes: createFreeFieldsPage,
    trace_acheminements_types_champs_libres: createFreeFieldsPage,
    trace_arrivages_types_champs_libres: createFreeFieldsPage,
    trace_services_types_champs_libres: createFreeFieldsPage,
    trace_mouvements_champs_libres: initializeStockMovementsFreeFields,
    donnees_imports: initializeImports,
    stock_receptions_champs_fixes_receptions: initializeReceptionFixedFields,
    trace_acheminements_champs_fixes: initializeDispatchFixedFields,
    trace_arrivages_champs_fixes: initializeArrivalFixedFields,
    trace_services_champs_fixes: initializeHandlingFixedFields,
    stock_demandes_livraisons: initializeDeliveries,
    stock_inventaires_frequences: initializeInventoryFrequenciesTable,
    stock_inventaires_categories: initializeInventoryCategoriesTable,
    stock_groupes_visibilite: initializeVisibilityGroup,
    utilisateurs_utilisateurs: initUserPage,
};

const slowOperations = [
    `FONT_FAMILY`,
    `MAX_SESSION_TIME`,
];

const $saveButton = $(`.save-settings`);
const $discardButton = $(`.discard-settings`);
const $managementButtons = $(`.save-settings, .discard-settings`);

$(function() {
    let canEdit = $(`input#edit`).val();

    updateMenu(submenu || menu, canEdit);

    $(`.settings-item`).on(`click`, function() {
        console.log(editing);
        if (!editing || (editing && window.confirm("Vous avez des modifications en attente, souhaitez vous continuer ?"))) {
            const selectedMenu = $(this).data(`menu`);

            $(`.settings-item.selected`).removeClass(`selected`);
            $(this).addClass(`selected`);
            updateMenu(selectedMenu, canEdit);
            editing = false;
        }
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
            form.element.find(`[data-table-processing]`).each(function() {
                const datatable = EditableDatatable.of(this);
                if (datatable) {
                    const tableData = datatable.data();
                    tables[$(this).data(`table-processing`)] = tableData;
                    tablesToReload.push(datatable);
                    hasErrors = tableData.filter(row => !row).length > 0;
                }
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

        if(slow) {
            Flash.add(`info`, `Mise à jour des paramétrage en cours, cette opération peut prendre quelques minutes`, false);
        }

        $saveButton.pushLoader('white');
        await AJAX.route(`POST`, `settings_save`)
            .json(data)
            .then(result => {
                if(result.success) {
                    for(const table of tablesToReload) {
                        if(table.mode !== MODE_EDIT) {
                            table.toggleEdit(STATE_VIEWING, true);
                        }
                    }
                }

                $saveButton.popLoader();
            })
            .catch(() => {
                $saveButton.popLoader();
            });
    });

    $discardButton.on('click', function() {
        if ($saveButton.hasClass(LOADING_CLASS)) {
            Flash.add(INFO, `Une opération est en cours de traitement`);
            return;
        }

        window.location.reload();
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
    $managementButtons.toggleClass('d-none', !displaySaveButton);

    let title;
    if(!submenu) {
        menu = selectedMenu;
        title = `${getCategoryLabel()} | <span class="bold">${getMenuLabel()}</span>`;
    } else {
        submenu = selectedMenu;

        const route = Routing.generate(`settings_item`, {category});
        const categoryLabel = category !== `trace` ? `<a href="${route}">${getCategoryLabel()}</a>` : getCategoryLabel();
        title = `${categoryLabel} | ${getMenuLabel()} | <span class="bold">${getSubmenuLabel()}</span>`;
    }

    const path = `${category}_${menu}` + (submenu ? `_` + submenu : ``);
    const $element = $(`[data-path="${path}"]`);

    if(!forms[path]) {
        forms[path] = {
            element: $element,
            ...(initializers[path] ? initializers[path]($element, canEdit) : []),
        };

        console.log(initializers[path] ? `Initializing ${path}` : `No initializer for ${path}`);
    }
    currentForm = path;

    const $pageTitle = $(`#page-title`);
    $pageTitle.html(title);
    const textTitle = $pageTitle.text();
    document.title = `Paramétrage | ${textTitle}`;

    const requestQuery = GetRequestQuery() || {};
    history.pushState({}, title, Routing.generate(`settings_item`, {
        ...requestQuery,
        category,
        menu,
        submenu,
    }));
}

function initializeWorkingHours($container, canEdit) {
    $managementButtons.addClass('d-none');

    const table = EditableDatatable.create(`#table-working-hours`, {
        route: Routing.generate('settings_working_hours_api', true),
        mode: canEdit ? MODE_DOUBLE_CLICK : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        needsPagingHide: true,
        onEditStart: () => {
            editing = true;
            $managementButtons.removeClass('d-none')
        },
        onEditStop: () => {
            editing = false;
            $managementButtons.addClass('d-none')
        },
        columns: [
            {data: `day`, title: `Jour`},
            {data: `hours`, title: `Horaires de travail<br><div class='wii-small-text'>Horaires sous la forme HH:MM-HH:MM;HH:MM-HH:MM</div>`},
            {data: `worked`, title: `Travaillé`},
        ],
    });
}

function initializeOffDays($container, canEdit) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);

    $managementButtons.addClass('d-none');

    const table = EditableDatatable.create(`#table-off-days`, {
        route: Routing.generate(`settings_off_days_api`, true),
        deleteRoute: `settings_off_days_delete`,
        mode: canEdit ? MODE_ADD_ONLY : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: true,
        paginate: true,
        needsPagingHide: true,
        needsSearchHide: true,
        onInit: () => {
            $addButton.removeClass(`d-none`);
        },
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
            $tableHeader.addClass('d-none');
        },
        onEditStop: () => {
            $managementButtons.removeClass('d-none');
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

function initializeReceptionFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-reception-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `réception`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paginate: false,
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
        },
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
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
        },
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
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
        },
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
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
        },
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

function initializeDeliveries() {
    initDeliveryRequestDefaultLocations();
    $('.new-type-association-button').on('click', function () {
        newTypeAssociation($(this));
    });

    $('.delete-association-line').on('click', function () {
        removeAssociationLine($(this));
    });
    $(document).arrive('.delete-association-line', function () {
        $(this).on('click', function () {
            removeAssociationLine($(this));
        });
    });

    $('select[name=deliveryType]').on('change', function () {
        onTypeChange($(this));
    });
    $(document).arrive('select[name=deliveryType]', function() {
        $(this).on('change', function () {
            onTypeChange($(this));
        });
    });
}

function initDeliveryRequestDefaultLocations() {
    const $deliveryTypeSettings = $(`input[name=deliveryTypeSettings]`);
    const deliveryTypeSettingsValues = JSON.parse($deliveryTypeSettings.val());

    deliveryTypeSettingsValues.forEach(item => {
        newTypeAssociation($(`button.new-type-association-button`), item.type, item.location);
    });
}

function newTypeAssociation($button, type = undefined, location = undefined) {
    const $settingTypeAssociation = $(`.setting-type-association`);
    const $typeTemplate = $(`#type-template`);

    let allFilledSelect = true;
    $settingTypeAssociation.find(`select[name=deliveryRequestLocation]`).each(function() {
        if(!$(this).val()) {
            allFilledSelect = false;
        }
    });

    if (allFilledSelect) {
        $button.prop(`disabled`, true);
        $settingTypeAssociation.append($typeTemplate.html());

        const $typeSelect = $settingTypeAssociation.last().find(`select[name=deliveryType]`);
        const $locationSelect = $settingTypeAssociation.last().find(`select[name=deliveryRequestLocation]`);

        if (type && location) {
            appendSelectOptions($typeSelect, $locationSelect, type, location);
        } else if (location) {
            let type = {
                id: 'all',
                label: 'Tous les types'
            }
            appendSelectOptions($typeSelect, $locationSelect, type, location);
        }
    } else {
        showBSAlert(`Tous les emplacements doivent être renseignés`, `danger`);
    }
}

function onTypeChange($select) {
    const $settingTypeAssociation = $select.closest('.setting-type-association');
    const $newTypeAssociationButton = $('.new-type-association-button');
    const $allTypeSelect = $settingTypeAssociation.find(`select[name=deliveryType]`);
    const $lastDeliveryTypeSelect = $allTypeSelect.last();

    const $typeAssociationContainer = $select.closest('.type-association-container');
    const $associatedLocation = $typeAssociationContainer.find('select[name="deliveryRequestLocation"]');
    $associatedLocation.val(null).trigger('change');
    setAlreadyDefinedTypes();

    let allFilledSelect = true;
    $allTypeSelect.each(function() {
        if(!$(this).val()) {
            allFilledSelect = false;
        }
    });

    let isAllSpecifiedTypes = false;
    if($lastDeliveryTypeSelect === $select) {
        isAllSpecifiedTypes = $select.data("length") <= 1;
    }

    $newTypeAssociationButton.prop(`disabled`, $select.val() === `all` || !allFilledSelect || isAllSpecifiedTypes);
}

function removeAssociationLine($button) {
    const $typeAssociationContainer = $('.type-association-container');

    if($typeAssociationContainer.length === 1) {
        showBSAlert('Au moins une association type/emplacement est nécessaire', 'danger')
    } else {
        $button.parent().parent(`.type-association-container`).remove();
        $('.new-type-association-button').prop(`disabled`, false);
    }
}

function setAlreadyDefinedTypes() {
    const $settingTypeAssociation = $('.setting-type-association');

    let types = [];
    $settingTypeAssociation.find(`select[name=deliveryType]`).each(function() {
        types.push($(this).val());
    });

    $('input[name=alreadyDefinedTypes]').val(types.join(';'));
}

function appendSelectOptions(typeSelect, locationSelect, type, location) {
    typeSelect
        .append(new Option(type.label, type.id, false, true))
        .trigger(`change`);

    locationSelect
        .append(new Option(location.label, location.id, false, true))
        .trigger(`change`);
}

function initializeInventoryFrequenciesTable(){
    $managementButtons.addClass('d-none');

    const table = EditableDatatable.create(`#frequencesTable`, {
        route: Routing.generate('settings_frequencies_api', true),
        deleteRoute: `settings_delete_frequency`,
        mode: MODE_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
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

function initializeInventoryCategoriesTable(){
    $managementButtons.addClass('d-none');
    const $frequencyOptions = JSON.parse($(`#frequency_options`).val());

    const table = EditableDatatable.create(`#categoriesTable`, {
        route: Routing.generate('settings_categories_api', true),
        deleteRoute: `settings_delete_category`,
        mode: MODE_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
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
            frequency: `<select name='frequency' class='form-control data needed' data-global-error="Fréquence">`+$frequencyOptions+`</select>`,
            permanent: `<div class='checkbox-container'><input type='checkbox' name='permanent' class='form-control data'/></div>`,
        },
    });
}


function initializeVisibilityGroup($container, canEdit) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);

    $managementButtons.addClass('d-none');

    const table = EditableDatatable.create(`#table-visibility-group`, {
        route: Routing.generate(`settings_visibility_group_api`, true),
        deleteRoute: `settings_visibility_group_delete`,
        mode: canEdit ? MODE_DOUBLE_CLICK : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onInit: () => {
            $addButton.removeClass(`d-none`);
        },
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
            $tableHeader.addClass('d-none');
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
            $tableHeader.removeClass('d-none');
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libelle`},
            {data: `description`, title: `Description`},
            {data: `actif`, title: `Actif`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed'  data-global-error="Libellé"/>`,
            description: `<input type='text' name='description' class='form-control data needed'  data-global-error="Description"/>`,
            actif: `<div class='checkbox-container'><input type='checkbox' name='actif' class='form-control data'/></div>`,
        },
    });

    $addButton.on(`click`, function() {
        table.addRow();
    });
}
