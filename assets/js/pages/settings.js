import '../../scss/pages/settings.scss';
import EditableDatatable, {MODE_ADD_ONLY, MODE_DOUBLE_CLICK, MODE_EDIT, MODE_EDIT_AND_ADD, SAVE_MANUALLY} from "../editatable";
import Flash from '../flash';

const settings = JSON.parse($(`input#settings`).val());
let category = $(`input#category`).val();
let menu = $(`input#menu`).val();
let submenu = $(`input#submenu`).val();

let currentForm = null;
const forms = {};
const initializers = {
    global_heures_travaillees: initializeWorkingHours,
    global_jours_non_travailles: initializeOffDays,
    global_apparence_site: initializeSiteAppearance,
    global_etiquettes: initializeGlobalLabels,
    stock_inventaires_frequences: initializeFrequencesTable,
    stock_inventaires_categories: initializeCategoriesTable,
};

const slowOperations = [
    `FONT_FAMILY`,
    `MAX_SESSION_TIME`,
];

const $saveButton = $(`.save-settings`);

$(document).ready(() => {
    updateTitle(submenu || menu);

    $(`.settings-item`).on(`click`, function() {
        const selectedMenu = $(this).data(`menu`);

        $(`.settings-item.selected`).removeClass(`selected`);
        $(this).addClass(`selected`);

        $(`.settings main .wii-box`).addClass(`d-none`);
        $(`.settings main .wii-box[data-menu="${selectedMenu}"]`).removeClass(`d-none`);

        updateTitle(selectedMenu);
    });

    $saveButton.on(`click`, async function() {
        const form = forms[currentForm];
        const tablesToReload = [];
        let data = Form.process(form.element, {
            ignored: `[data-table-processing]`,
        });

        if(data) {
            const tables = {};
            form.element.find(`[id][data-table-processing]`).each(function() {
                const datatable = EditableDatatable.of(this);
                tables[$(this).data(`table-processing`)] = datatable.data();
                tablesToReload.push(datatable);
            });

            if(Object.entries(tables).length) {
                data.append(`datatables`, JSON.stringify(tables));
            }
        }

        const slow = Object.keys(data.asObject()).find(function(n) {
            return slowOperations.indexOf(n) !== -1;
        });

        if(slow) {
            Flash.add(`info`, `Mise à jour des paramétrage en cours, cette opération peut prendre quelques minutes`, false);
        }

        await AJAX.route(`POST`, `settings_save`)
            .json(data)
            .then(() => {
                for(const table of tablesToReload) {
                    table.editable = false;
                    table.toggleEdit(false, true);
                }
            });
    });
});

function getCategoryLabel() {
    return settings[category].label;
}

function getMenuLabel() {
    const menuData = settings[category].menus[menu];

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
        return settings[category].menus[menu].menus[submenu];
    }
}

function updateTitle(selectedMenu) {
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

    $saveButton.show();

    if(!forms[path]) {
        currentForm = path;
        forms[path] = {
            element: $element,
            ...(initializers[path] ? initializers[path]($element) : []),
        };
    }

    $(`#page-title`).html(title);
    document.title = `Paramétrage | ${title}`;

    let urlParts = (window.location.href).split(`/`);
    urlParts[urlParts.length - 1] = selectedMenu;

    const url = urlParts.join(`/`);
    history.pushState({}, title, url);
}

function initializeWorkingHours($container) {
    $saveButton.hide();

    const table = EditableDatatable.create(`#table-working-hours`, {
        route: Routing.generate('settings_working_hours_api', true),
        edit: MODE_DOUBLE_CLICK,
        save: SAVE_MANUALLY,
        onEditStart: () => $saveButton.show(),
        onEditStop: () => $saveButton.hide(),
        columns: [
            {data: `day`, title: `Jour`},
            {data: `hours`, title: `Horaires de travail`},
            {data: `worked`, title: `Travaillé`},
        ],
    });
}

function initializeOffDays($container) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);

    $saveButton.hide();

    const table = EditableDatatable.create(`#table-off-days`, {
        route: Routing.generate(`settings_off_days_api`, true),
        deleteRoute: `settings_off_days_delete`,
        edit: MODE_ADD_ONLY,
        save: SAVE_MANUALLY,
        search: true,
        paginate: true,
        onInit: () => {
            $addButton.removeClass(`d-none`);
        },
        onEditStart: () => {
            $saveButton.show();
            $tableHeader.hide();
        },
        onEditStop: () => {
            $saveButton.hide();
            $tableHeader.show();
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
    updateImagePreview('#preview-website-logo', '#upload-website-logo');
    updateImagePreview('#preview-email-logo', '#upload-email-logo');
    updateImagePreview('#preview-mobile-logo-login', '#upload-mobile-logo-login');
    updateImagePreview('#preview-mobile-logo-header', '#upload-mobile-logo-header');
}

function initializeGlobalLabels() {
    updateImagePreview('#preview-label-logo', '#upload-label-logo');
}

function initializeFrequencesTable(){
    $saveButton.hide();

    const table = EditableDatatable.create(`#frequencesTable`, {
        route: Routing.generate('frequences_api', true),
        deleteRoute: `settings_delete_frequence`,
        edit: MODE_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $saveButton.show();
        },
        onEditStop: () => {
            $saveButton.hide();
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libelle`},
            {data: `nb_months`, title: `Nombre de mois`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data'/>`,
            nb_months: `<input type='number' name='nbMonths' min='1' class='data form-control'/>`,
        },
    });
}

function initializeCategoriesTable(){
    $saveButton.hide();
    const $frequencyOptions = JSON.parse($(`#frequency_options`).val());

    const table = EditableDatatable.create(`#categoriesTable`, {
        route: Routing.generate('categories_api', true),
        deleteRoute: `settings_delete_category`,
        edit: MODE_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $saveButton.show();
        },
        onEditStop: () => {
            $saveButton.hide();
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libelle`},
            {data: `frequency`, title: `Fréquence`},
            {data: `permanent`, title: `Permanent`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row w-50'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data w-50'/>`,
            frequency: `<select name='frequency' class='form-control data needed w-50'>`+$frequencyOptions+`</select>`,
            permanent: `<div class='checkbox-container'><input type='checkbox' name='permanent' class='form-control data'/></div>`,
        },
    });
}
