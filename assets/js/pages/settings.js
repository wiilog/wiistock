import '../../scss/pages/settings.scss';
import EditableDatatable, {MODE_ADD_ONLY, MODE_DOUBLE_CLICK, SAVE_MANUALLY} from "../editatable";
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
};

const slowOperations = [
    `FONT_FAMILY`,
    `MAX_SESSION_TIME`,
]

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
        let data = Form.process(form.element, {
            ignored: `[data-table-processing]`,
        });

        if(data) {
            form.element.find(`[id][data-table-processing]`).each(function() {
                data.append($(this).data(`table-processing`), EditableDatatable.of(this).data());
            });
        }

        const slow = Object.keys(data.asObject()).filter(function(n) {
            return slowOperations.indexOf(n) !== -1;
        });

        if(slow.length) {
            Flash.add(`info`, `Mise à jour des paramétrage en cours, cette opération peut prendre quelques minutes`, false);
        }

        await AJAX.route(`POST`, `settings_save`).json(data);
    });
})

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

    if (!forms[path]) {
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

    $saveButton.hide();

    const table = EditableDatatable.create(`#table-off-days`, {
        route: Routing.generate('settings_off_days_api', true),
        edit: MODE_ADD_ONLY,
        save: SAVE_MANUALLY,
        search: true,
        paginate: true,
        onEditStart: () => {
            $saveButton.show();
            $addButton.show()
            $(`.wii-page-card-header`).hide();
        },
        onEditStop: () => {
            $saveButton.hide();
            $(`.wii-page-card-header`).show();
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `day`, title: `Jour`},
        ],
        form: {
            actions: ``,
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
