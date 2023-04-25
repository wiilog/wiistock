import '@styles/pages/settings.scss';
import EditableDatatable, {MODE_ADD_ONLY, MODE_CLICK_EDIT, MODE_NO_EDIT, SAVE_MANUALLY, STATE_VIEWING, MODE_EDIT, MODE_CLICK_EDIT_AND_ADD, } from "../../editatable";
import Flash, {INFO} from '@app/flash';
import {LOADING_CLASS} from "@app/loading";
import {initUserPage} from "./users/users";
import {initializeExports, initializeImports} from "./data/imports.js";
import {initializeRolesPage} from "./users/roles";
import {initializeRequestTemplates} from "./request-template";
import {initializeTouchTerminal} from "./kiosk";
import {initializeTransportRound} from "./transport-round";
import {
    initializeStockArticlesTypesFreeFields,
    createFreeFieldsPage,
    initializeTraceMovementsFreeFields,
    initializeIotFreeFields,
    initializeReceptionsFreeFields, createArrivalsFreeFieldsPage, createDispatchFreeFieldsPage, createHandlingFreeFieldsPage, createDeliveryRequestFieldsPage,
} from "./free-fields";
import {
    initializeArrivalDisputeStatuses,
    initializeReceptionDisputeStatuses,
    initializePurchaseRequestStatuses,
    initializeArrivalStatuses,
    initializeDispatchStatuses,
    initializeHandlingStatuses
} from "./statuses";
import {initializeAlertTemplate, initializeNotifications} from "./alert-template";
import {onHeaderPageEditStop} from "./utils";
import Form from '../../form';
import Routing from '../../../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
import AJAX, {GET, POST} from "@app/ajax";
import {initializeInventoryPlanificatorTable} from "@app/pages/settings/inventory/inventoryPlanner";
import {initializePurchaseRequestPlanner} from "@app/pages/settings/purchase-request/planner";

global.triggerReminderEmails = triggerReminderEmails;
global.saveTranslations = saveTranslations;
global.addTypeRow = addTypeRow;
global.removeTypeRow = removeTypeRow;
global.deleteTemplate = deleteTemplate;
global.changeReceiverInput = changeReceiverInput;

const index = JSON.parse($(`input#settings`).val());
let category = $(`input#category`).val();
let menu = $(`input#menu`).val();
let submenu = $(`input#submenu`).val();

let currentForm = null;
const forms = {};


//keys are from url with / replaced by _
//http://wiistock/parametrage/afficher/stock/receptions/champs_fixes => stock_receptions_champs_fixes
const initializers = {
    global_heures_travaillees: initializeWorkingHours,
    global_jours_non_travailles: initializeOffDays,
    global_apparence_site: initializeSiteAppearance,
    global_etiquettes: initializeGlobalLabels,
    stock_articles_etiquettes: initializeStockArticlesLabels,
    stock_articles_types_champs_libres: initializeStockArticlesTypesFreeFields,
    stock_articles_champs_fixes: initializeArticleFixedFields,
    stock_demandes_types_champs_libres_livraisons: createDeliveryRequestFieldsPage,
    stock_demandes_types_champs_libres_collectes: createFreeFieldsPage,
    track_demande_transport_types_champs_libres_livraisons: createFreeFieldsPage,
    track_demande_transport_types_champs_libres_collectes: createFreeFieldsPage,
    trace_acheminements_types_champs_libres: createDispatchFreeFieldsPage,
    trace_arrivages_types_champs_libres: createArrivalsFreeFieldsPage,
    trace_services_types_champs_libres: createHandlingFreeFieldsPage,
    trace_mouvements_champs_libres: initializeTraceMovementsFreeFields,
    stock_receptions_champs_libres: initializeReceptionsFreeFields,
    trace_services_modeles_demande: initializeRequestTemplates,
    notifications_alertes: initializeAlertTemplate,
    notifications_notifications_push: initializeNotifications,
    iot_types_champs_libres: initializeIotFreeFields,
    donnees_imports: initializeImports,
    donnees_exports_csv: initializeExports,
    stock_receptions_champs_fixes: initializeReceptionFixedFields,
    stock_demandes_champs_fixes: initializeDemandesFixedFields,
    trace_acheminements_champs_fixes: initializeDispatchFixedFields,
    trace_arrivages_champs_fixes: initializeArrivalFixedFields,
    trace_services_champs_fixes: initializeHandlingFixedFields,
    stock_inventaires_frequences: initializeInventoryFrequenciesTable,
    stock_inventaires_categories: initializeInventoryCategoriesTable,
    stock_inventaires_planificateur: initializeInventoryPlanificatorTable,
    stock_groupes_visibilite: initializeVisibilityGroup,
    stock_borne_tactile: initializeTouchTerminal,
    utilisateurs_utilisateurs: initUserPage,
    trace_arrivages_statuts_litiges: initializeArrivalDisputeStatuses,
    trace_acheminements_statuts: initializeDispatchStatuses,
    trace_services_statuts: initializeHandlingStatuses,
    stock_receptions_statuts_litiges: initializeReceptionDisputeStatuses,
    utilisateurs_roles: initializeRolesPage,
    stock_receptions_types_litiges: initializeReceptionTypesLitige,
    trace_arrivages_types_litiges: initializeTraceArrivalTypesLitige,
    trace_arrivages_statuts: initializeArrivalStatuses,
    stock_demandes_statuts_achats: initializePurchaseRequestStatuses,
    stock_demandes_modeles_demande_livraisons: initializeRequestTemplates,
    stock_demandes_modeles_demande_collectes: initializeRequestTemplates,
    stock_demandes_planification_achats: initializePurchaseRequestPlanner,
    track_tournees: initializeTransportRound,
    modeles_livraison_lettre_de_voiture: initializeDeliveryWaybillTemplate,
    modeles_acheminement_lettre_de_voiture: initializeDeliveryWaybillTemplate,
    modeles_acheminement_compte_rendu: initializeDeliveryWaybillTemplate,
    stock_articles_pays_d_origine: initializeArticleNativeCountriesTable,
    trace_arrivages_camion_champs_fixes: initializeTruckArrivalFixedFields,
    trace_urgences_champs_fixes: initializeEmergenciesFixedFields,
};

const saveCallbacks = {
    global_apparence_site: () => location.reload(),
    notifications_alertes: ($container, apiResult) => onHeaderPageEditStop($container, apiResult)
};

const slowOperations = [
    `MAX_SESSION_TIME`,
];

const $saveButton = $(`.save-settings`);
const $discardButton = $(`.discard-settings`);
const $managementButtons = $(`.save-settings, .discard-settings`);
let canTranslate = true;
let editing = false;

$(function() {
    let canEdit = $(`input#edit`).val();

    updateMenu(submenu || menu, canEdit);


    $(document).on(`change`, `.wii-box.settings-content:not(.d-none) *`, function() {
        editing = true;
    });

    document.body.addEventListener(`click`, function(event) {
        const $target = $(event.target);

        let $button;
        if($target.is(`.delete-row-view`)) {
            $button = $target;
        } else if($target.closest(`.delete-row-view`).exists()) {
            $button = $target.closest(`.delete-row-view`);
        } else {
            return;
        }

        event.handled = true;
        event.stopPropagation();
        event.preventDefault();

        const id = $button.data(`id`);
        const type = $button.data(`type`);

        AJAX.route(`POST`, `settings_delete_row`, {id, type})
            .json()
            .then(({success}) => {
                if (success) {
                    $target.closest(`tr`).remove();
                }
            });
    }, true);

    $(`.settings-item`).on(`click`, function() {
        if (!editing || window.confirm(`Vous avez des modifications en attente, souhaitez-vous continuer ?`)) {
            const selectedMenu = $(this).data(`menu`);
            $(`.settings-item.selected`).removeClass(`selected`);
            $(this).addClass(`selected`);
            updateMenu(selectedMenu, canEdit);

            if(editing) {
                window.location.reload();
            }
        }
    });

    $saveButton.on(`click`, async function() {
        const form = forms[currentForm];
        const tablesToReload = [];
        const config = {ignored: `[data-table-processing]`,};

        const data = Form.process(form.element, config);
        let hasErrors = false;
        if(data) {
            const fieldNames = Form.getFieldNames(form.element, config);
            data.set('__form_fieldNames', JSON.stringify(fieldNames));

            const tables = {};

            try {
                form.element.find(`[data-table-processing]`).each(function () {
                    const datatable = EditableDatatable.of(this);
                    if (datatable && $(this).data('needs-processing')) {
                        const tableData = datatable.data();
                        tables[$(this).data(`table-processing`)] = tableData;
                        tables[`category`] = $(this).data(`category`);
                        tablesToReload.push(datatable);
                        hasErrors = tableData.filter(row => !row).length > 0;
                    }
                });
            } catch(error) {
                Flash.add(`danger`, error);
                return;
            }

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

        editing = false;

        await AJAX.route(`POST`, `settings_save`)
            .json(data)
            .then(result => {
                if(result.success) {
                    let params = undefined;
                    if (result && result.entity) {
                        params = {entity: result.entity};
                    }
                    for(const table of tablesToReload) {
                        if(table.mode !== MODE_EDIT) {
                            table.toggleEdit(STATE_VIEWING, true, {params});
                        }
                    }

                    if(saveCallbacks[currentForm]) {
                        const $container = $(`[data-path=${currentForm}]`);
                        saveCallbacks[currentForm]($container, result);
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
            AJAX.route(`POST`, `settings_save_field_param`, {field})
                .json(data)
                .then((response) => {
                    if(response.success){
                        $modal.modal(`hide`);
                    }
                });
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

    const displaySaveButton = $selectedMenu.data('save-button');
    const displayDiscardButton = $selectedMenu.data('discard-button');
    $saveButton.toggleClass('d-none', !displaySaveButton);
    $discardButton.toggleClass('d-none', !displayDiscardButton);

    let title;
    if(!submenu) {
        menu = selectedMenu;
        title = `${getCategoryLabel()} | <span class="bold">${getMenuLabel()}</span>`;
    } else {
        submenu = selectedMenu;

        const route = Routing.generate(`settings_item`, {category});
        title = `<a href="${route}">${getCategoryLabel()}</a> | ${getMenuLabel()} | <span class="bold">${getSubmenuLabel()}</span>`;
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
    const table = EditableDatatable.create(`#table-working-hours`, {
        route: Routing.generate('settings_working_hours_api', true),
        mode: canEdit ? MODE_CLICK_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        needsPagingHide: true,
        onEditStart: () => {
            $managementButtons.removeClass('d-none')
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none')
        },
        columns: [
            {data: `day`, title: `Jour`},
            {data: `hours`, title: `Horaires de travail<br><div class='wii-small-text'>Horaires sous la forme HH:MM-HH:MM;HH:MM-HH:MM</div>`},
            {data: `worked`, title: `Travaillé`},
        ],
    });
    initializeHourShifts($container, canEdit);
}

function initializeHourShifts($container, canEdit) {
    const table = EditableDatatable.create(`#table-hour-shifts`, {
        route: Routing.generate('settings_hour_shift_api', true),
        mode: canEdit ? MODE_CLICK_EDIT_AND_ADD : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        needsPagingHide: true,
        onEditStart: () => {
            $managementButtons.removeClass('d-none')
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none')
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `name`, title: `Nom du créneau`},
            {data: `hours`, title: `Heures<br><div class='wii-small-text'>Horaires sous la forme HH:MM-HH:MM</div>`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            name: `<input name='name' class='form-control data' data-global-error='Nom du créneau'/>`,
            hours: `<input name='hours' class='form-control data' data-global-error='Heures'/>`,
        },
    });
}

function initializeOffDays($container, canEdit) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);

    //add a custom sort to use the data-timestamp attribute
    //to sort dates instead of sorting alphabetically
    $.extend($.fn.dataTableExt.oSort, {
        ["date-sort-pre"]: field => $(field).data(`timestamp`),
        ["date-sort-asc"]: (a, b) => ((a < b) ? 1 : ((a > b) ? -1 : 0)),
        ["date-sort-desc"]: (a, b) => ((a < b) ? -1 : ((a > b) ? 1 : 0)),
    })

    const table = EditableDatatable.create(`#table-off-days`, {
        route: Routing.generate(`settings_off_days_api`, true),
        deleteRoute: `settings_off_days_delete`,
        mode: canEdit ? MODE_ADD_ONLY : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: true,
        paging: true,
        ordering: true,
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
            {targets: 1, width: `100%`},
            {targets: 1, type: `date-sort`},
        ],
    });

    $addButton.on(`click`, function() {
        table.addRow(true);
    });
}

function initializeSiteAppearance() {
    // $('#upload-website-logo').on('change', () => updateImagePreview('#preview-website-logo', '#upload-website-logo'));
    // $('#upload-email-logo').on('change', () => updateImagePreview('#preview-email-logo', '#upload-email-logo'));
    // $('#upload-mobile-logo-login').on('change', () => updateImagePreview('#preview-mobile-logo-login', '#upload-mobile-logo-login'));
    // $('#upload-mobile-logo-header').on('change', () => updateImagePreview('#preview-mobile-logo-header', '#upload-mobile-logo-header'));
}

function initializeGlobalLabels() {
    $('#upload-label-logo').on('change', () => updateImagePreview('#preview-label-logo', '#upload-label-logo'));

    const $typeOptions = JSON.parse($(`#type_options`).val());
    const $natureOptions = JSON.parse($(`#nature_options`).val());

    const table = EditableDatatable.create(`#tagTemplateTable`, {
        route: Routing.generate('settings_tag_template_api', true),
        deleteRoute: `settings_delete_tag_template`,
        mode: MODE_CLICK_EDIT_AND_ADD,
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
            {data: `prefix`, title: `Préfixe`, required: true},
            {data: `barcodeType`, title: `Type d'étiquette`, required: true},
            {data: `height`, title: `Hauteur (mm)`, required: true},
            {data: `width`, title: `Largeur (mm)`, required: true},
            {data: `module`, title: `Brique`, required: true},
            {data: `natureOrType`, title: `Nature(s) / Type(s)`, required: true},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            prefix: `<input type='text' name='prefix' class='form-control data needed' data-global-error='Préfixe'/>`,
            barcodeType: () => {
                const elements = [{label: 'Code 128', value: '1', checked: false}, {label: 'QR Code', value: '0', checked: true}]
                    .map(({label, value, checked}) => {
                        const id = 'barcodeType-' + Math.floor(Math.random() * 1000000);
                        return `
                            <input type='radio' id='${id}' name='barcodeType' class='form-control data d-none' value='${value}' ${checked ? 'checked' : ''} content='${label}'>
                            <label for="${id}">${label}</label>
                        `;
                    })
                    .join('');
                return `<form><div class='wii-switch needed' data-title="Type d'étiquette" data-name="barcodeType">${elements}</div></form>`;
            },
            height: `<input type='number' name='height' class='form-control data needed' data-global-error='Hauteur'/>`,
            width: `<input type='number' name='width' class='form-control data needed' data-global-error='Largeur'/>`,
            module: `<select name='module' class='form-control data needed' data-global-error='Brique'>
                        <option value='arrivage' selected>Arrivage</option>
                        <option value='article'>Article</option>
                    </select>`,
            natureOrType: `<select name='natureOrType' multiple data-s2="natureOrTypeSelect" data-include-params-parent="tr" data-include-params='select[name=module]' class='form-control data needed' data-global-error='Nature(s) / Type(s)'>`+$typeOptions+`</select>`,
        },
    });

    $(window).on('resize', function () {
        table.table.columns.adjust();
    });
}

function initializeStockArticlesLabels($container) {
    const destination = `INCLURE_EMPLACEMENT_DESTINATION_SUR_ETIQUETTE_ARTICLE_RECEPTION`;
    const recipient = `INCLURE_DROPZONE_DESTINATAIRE_SUR_ETIQUETTE_ARTICLE_RECEPTION`;

    const $destination = $container.find(`[name=${destination}]`);
    const $recipient = $container.find(`[name=${recipient}]`);

    $container.find(`[name=SHOW_LOCATION]`).on(`click`, function() {
        const checked = $(this).prop(`checked`);

        $destination.closest(`.wii-radio-container`).toggleClass(`d-none`, !checked);

        if(checked) {
            $destination.val(1);
            $recipient.val(0);
            $destination.prop(`checked`, true);
        } else {
            $destination.val(0);
            $recipient.val(0);
            $destination.prop(`checked`, false);
            $recipient.prop(`checked`, false);
        }
    })

    $destination.on(`click`, function() {
        $destination.val(Number($destination.prop(`checked`)));
        $recipient.val(Number(!$destination.prop(`checked`)));
        $recipient.prop(`checked`, false);
    })

    $recipient.on(`click`, function() {
        $recipient.val(Number($recipient.prop(`checked`)));
        $destination.val(Number(!$recipient.prop(`checked`)));
        $destination.prop(`checked`, false);
    })

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
        paging: false,
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

function initializeDemandesFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-demande-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `demande`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paging: false,
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

    initializeLocationByTypeForDeliveries();
}

function initializeDispatchFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-dispatch-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `acheminements`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paging: false,
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

function initializeArticleFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-article-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `articles`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paging: false,
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
        paging: false,
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
            {data: `keptInMemory`, title: `Garder en mémoire`},
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
        paging: false,
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
    initializeType();
}

function initializeLocationByTypeForDeliveries() {
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
    const $buttonNewTypeAssociation = $(`button.new-type-association-button`);

    deliveryTypeSettingsValues.forEach(item => {
        newTypeAssociation($buttonNewTypeAssociation, item.type, item.location, true);
        updateAlreadyDefinedTypes();
    });
    const $lastDeliveryTypeSelect = $('select[name=deliveryType]').last();

    $buttonNewTypeAssociation.prop('disabled', $buttonNewTypeAssociation.is(`[data-keep-disabled]`) || $lastDeliveryTypeSelect.data('length') < 1);
}

function newTypeAssociation($button, type = undefined, location = undefined, firstLoad = false) {
    const $modal = $('#modal-fixed-field-destinationdemande');
    const $settingTypeAssociation = $modal.find(`.setting-type-association`);
    const $typeTemplate = $(`#type-template`);

    let allFilledSelect = true;
    $settingTypeAssociation.find(`select[name=deliveryRequestLocation]`).each(function() {
        if(!$(this).val()) {
            allFilledSelect = false;
        }
    });

    if (firstLoad || allFilledSelect) {
        $button.prop(`disabled`, false);
        $settingTypeAssociation.append($typeTemplate.html());
        if(firstLoad && location && type) {
            const $typeSelect = $settingTypeAssociation.last().find(`select[name=deliveryType]`);
            const $locationSelect = $settingTypeAssociation.last().find(`select[name=deliveryRequestLocation]`);
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

    const $typeAssociationContainer = $select.closest('.type-association-container');
    const $associatedLocation = $typeAssociationContainer.find('select[name="deliveryRequestLocation"]');
    $associatedLocation.val(null).trigger('change');
    updateAlreadyDefinedTypes();

    let allFilledSelect = true;
    $allTypeSelect.each(function() {
        if(!$(this).val()) {
            allFilledSelect = false;
        }
    });

    $newTypeAssociationButton.prop(`disabled`, $select.val() === `all` || !allFilledSelect || $select.data("length") <= 1);
}

function removeAssociationLine($button) {
    const $typeAssociationContainer = $('.type-association-container');
    const $currentDeliveryTypeSelect = $button.parent().closest('.delivery-type-container').find('select[name=deliveryType]');

    if($typeAssociationContainer.length === 1) {
        showBSAlert('Au moins une association type/emplacement est nécessaire', 'danger')
    } else {
        $button.parent().parent(`.type-association-container`).remove();
        updateAlreadyDefinedTypes($currentDeliveryTypeSelect.val());
        $('.new-type-association-button').prop(`disabled`, false);
    }
}

function updateAlreadyDefinedTypes(withdrawedValue = undefined) {
    const $settingTypeAssociation = $('.setting-type-association');

    let types = [];
    $settingTypeAssociation.find(`select[name=deliveryType]`).each(function() {
        if(withdrawedValue !== $(this).val()) {
            types.push($(this).val());
        }
    });

    $('input[name=alreadyDefinedTypes]').val(types.join(';'));
}

function initializeInventoryFrequenciesTable(){
    const table = EditableDatatable.create(`#frequencesTable`, {
        route: Routing.generate('settings_frequencies_api', true),
        deleteRoute: `settings_delete_frequency`,
        mode: MODE_CLICK_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paging: false,
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
            {data: `label`, title: `Libellé`, required: true},
            {data: `nb_months`, title: `Nombre de mois`, required: true},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed' data-global-error="Libellé"/>`,
            nb_months: `<input type='number' name='nbMonths' min='1' class='data form-control needed' data-global-error="Nombre de mois"/>`,
        },
    });
}

function initializeInventoryCategoriesTable(){
    const $frequencyOptions = JSON.parse($(`#frequency_options`).val());

    const table = EditableDatatable.create(`#categoriesTable`, {
        route: Routing.generate('settings_categories_api', true),
        deleteRoute: `settings_delete_category`,
        mode: MODE_CLICK_EDIT_AND_ADD,
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
            {data: `label`, title: `Libellé`, required: true},
            {data: `frequency`, title: `Fréquence`, required: true},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed'  data-global-error="Libellé"/>`,
            frequency: `<select name='frequency' class='form-control data needed' data-global-error="Fréquence">`+$frequencyOptions+`</select>`,
        },
    });
}

function initializeReceptionTypesLitige(){
    $saveButton.addClass('d-none');
    $discardButton.addClass('d-none');

    const table = EditableDatatable.create(`#table-types-litige`, {
        route: Routing.generate('types_litige_api', true),
        deleteRoute: `settings_delete_type_litige`,
        mode: MODE_CLICK_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $saveButton.removeClass('d-none');
            $discardButton.removeClass('d-none');
        },
        onEditStop: () => {
            $saveButton.addClass('d-none');
            $discardButton.addClass('d-none');
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libellé`, required: true},
            {data: `description`, title: `Description`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed' data-global-error='Libellé'/>`,
            description: `<input type='text' name='description' class='form-control data' data-global-error='Description'/>`,
        },
    });
}

function initializeTraceArrivalTypesLitige($container){
    $saveButton.addClass('d-none');
    $discardButton.addClass('d-none');
    const $translateLabels = $container.find('.translate-labels');
    const $translateButton = $container.find(`.translate-labels-button`);
    const $modalEditTranslations = $container.find(".edit-translation-modal");

    const table = EditableDatatable.create(`#table-types-litige`, {
        route: Routing.generate('types_litige_api', true),
        deleteRoute: `settings_delete_type_litige`,
        mode: MODE_CLICK_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onEditStart: () => {
            $saveButton.removeClass('d-none');
            $discardButton.removeClass('d-none');

            if (canTranslate) {
                $translateLabels.removeClass('d-none');
            }

            $translateButton
                .off('click.disputeTypesTranslation')
                .on('click.disputeTypesTranslation', function () {
                    wrapLoadingOnActionButton($(this), () => (
                        AJAX.route(GET, "settings_edit_types_litige_translations_api")
                            .json()
                            .then((response) => {
                                $modalEditTranslations.find(`.modal-body`).html(response.html);
                                $modalEditTranslations.modal('show');
                            })
                    ));
                });
        },
        onEditStop: () => {
            $saveButton.addClass('d-none');
            $discardButton.addClass('d-none');
            if (canTranslate) {
                $translateLabels.addClass('d-none');
            }
            canTranslate = true;
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libellé`, required: true},
            {data: `description`, title: `Description`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed' data-global-error='Libellé'/>`,
            description: `<input type='text' name='description' class='form-control data' data-global-error='Description'/>`,
        },
    });

    let submitEditTranslations = $modalEditTranslations.find("[type=submit]");
    let urlEditTranslations = Routing.generate('settings_edit_types_litige_translations', true);
    InitModal($modalEditTranslations, submitEditTranslations, urlEditTranslations, {
        success: () => {
            table.toggleEdit(STATE_VIEWING, true);
        }
    });
}

function initializeVisibilityGroup($container, canEdit) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);
    changePageTitle($container.find('.wii-title'), false);

    const table = EditableDatatable.create(`#table-visibility-group`, {
        route: Routing.generate(`settings_visibility_group_api`, true),
        deleteRoute: `settings_visibility_group_delete`,
        mode: canEdit ? MODE_CLICK_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: false,
        ordering: true,
        paginate: true,
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

            changePageTitle($container.find('.wii-title'), false);
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libellé`, required: true},
            {data: `description`, title: `Description`, required: true},
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
        table.addRow(true);
        changePageTitle($container.find('.wii-title'), true);
    });
}

function appendSelectOptions(typeSelect, locationSelect, type, location) {
    typeSelect
        .append(new Option(type.label, type.id, false, true))
        .trigger(`change`);

    locationSelect
        .append(new Option(location.label, location.id, false, true))
        .trigger(`change`);
}

function triggerReminderEmails($button) {
    $button.pushLoader(`primary`);
    $.post(Routing.generate(`trigger_reminder_emails`), true).then(({success, msg}) => {
        $button.popLoader()
        Flash.add(success ? `success` : `danger`, msg);
    });
}

function changePageTitle($title, add) {
    $title.text(add ? 'Ajouter des groupes de visibilité' : 'Groupe de visibilité');
}

function saveTranslations($button) {
    $button.pushLoader(`white`);
    let $inputs = $('#translation').find('.translate');
    let data = [];
    $inputs.each(function () {
        let name = $(this).attr('name');
        let val = $(this).val();
        data.push({id: name, val: val});
    });

    let path = Routing.generate('save_translations');
    const $spinner = $('#spinnerSaveTranslations');
    showBSAlert('Mise à jour de votre personnalisation des libellés : merci de patienter.', 'success', false);
    loadSpinner($spinner);
    $.post(path, JSON.stringify(data), (resp) => {
        $button.popLoader();
        $('html,body').animate({scrollTop: 0});
        if (resp) {
            location.reload();
        } else {
            hideSpinner($spinner);
            showBSAlert('Une erreur est survenue lors de la personnalisation des libellés.', 'danger');
        }
    });
}

function initializeType() {
    applyEventForType($('.handling-type'));
}

function addTypeRow($button) {
    let field = $button.closest('.modal').find('.zone-type');
    let template = $button.closest('.modal').find('.row-template');
    let clone = template.clone().contents();
    let newMultipleKey = Math.floor(Math.random() * 100000000);
    field.append(clone);
    field.find($('select[name=user]').last()).data('multiple-object-index', newMultipleKey);
    field.find($('select[name=handlingType]').last()).data('multiple-object-index', newMultipleKey);
    applyEventForType(clone.find('.handling-type'));
    verifyAlreadyDefineTypes(clone.find('.handling-type'));
}

function removeTypeRow($button) {
    let rowType = $button.parents('.row-type');
    rowType.remove();
    verifyAlreadyDefineTypes(rowType.find('.handling-type'));
}

function applyEventForType(select2) {
    select2.on("change", function () {
        verifyAlreadyDefineTypes(select2);
    });
    select2.on("select2:opening", function () {
        verifyAlreadyDefineTypes(select2);
    });
}

function verifyAlreadyDefineTypes(select2) {
    const $alreadyDefinedTypes = select2.closest('.modal').find('input[name=alreadyDefinedTypes]');
    let values = [];
    let $handlingTypeContainer = $('.handling-type');
    $handlingTypeContainer.each(function() {
        if ($(this).val() && !values.includes($(this).val())) {
            values.push($(this).val());
        }
    });
    $alreadyDefinedTypes.val(values.join(','));
    const $types = JSON.parse($('.zone-type').closest('.modal').find('input[name=types]').val());
    if ($handlingTypeContainer.length < $types.length) {
        $('.add-row-type').attr("disabled", false);
    }
    if ($handlingTypeContainer.length >= $types.length) {
        $('.add-row-type').attr("disabled", true);
    }
}

function initializeDeliveryWaybillTemplate() {
    $(`.load-custom-template`).on(`click`, function () {
        $(this).parent().find(`.custom-template-file`).click();
    });

    $(`.custom-template-file`).on(`change`, function () {
        const file = $(this)[0].files.length > 0 ? $(this)[0].files[0] : undefined;
        const link = $(this).val();
        if (file && file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.template') {
            const name = file.name;
            const $parent = $(this).parent();
            const deleted_name = $parent.data('name') + '_DELETED';
            $parent.find(`.custom-template-file-name`).val(name);
            $parent.find(`.custom-template-preview`).html(`
                <p class="attachement" style="width: fit-content !important;">
                    <a class="wii-small-text" href="${link}" download="test">
                        <i class="fa fa-file-pdf mr-2"></i>${name}
                    </a>
                    <i class="fa fa-times red pointer ml-1"
                       onclick="removeAttachment($(this), deleteTemplate)"></i>
                </p>
            `)
            $(`input[name=${deleted_name}]`).val("0");
        } else {
            Flash.add(`danger`, `Veuillez sélectionner un fichier Microsoft Word valide.`)
        }
    });

    $('.wii-checkbox').on('click', function() {
        const $checkbox = $(this).find('input[type=checkbox]');
        const check = $checkbox.is(':checked');
        $('.wii-checkbox').find('input[type=checkbox]').prop("checked", !check);
        $checkbox.prop('checked', check);
    })
}

function deleteTemplate($elem) {
    const $parent = $elem.parents(`.custom-template`);
    const name = $parent.data('name') + '_DELETED';
    $parent.find(`.custom-template-preview`).html(`<span class="wii-small-text my-2">Aucun modèle personnalisé.</span>`);
    $parent.find(`.custom-template-file, .custom-template-file-name`).val(null);
    $(`input[name=${name}]`).val('1');
}

function initializeTruckArrivalFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-truck-arrival-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `truckArrivals`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paging: false,
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
    initializeType();
}

function initializeArticleNativeCountriesTable() {
    const table = EditableDatatable.create(`#nativeCountriesTable`, {
        route: Routing.generate('settings_native_countries_api', true),
        deleteRoute: `settings_delete_native_country`,
        mode: MODE_CLICK_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        search: false,
        paging: false,
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
            {data: 'code', title: 'Code', required: true},
            {data: `label`, title: `Libellé`, required: true},
            {data: `active`, title: `Actif`, required: true},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            code: `<input type='text' name='code' class='form-control data needed' data-global-error="Code"/>`,
            label: `<input type='text' name='label' class='form-control data needed' data-global-error="Libellé"/>`,
            active: `<div class='checkbox-container'><input type='checkbox' name='active' class='form-control data'/></div>`,
        },
    });
}

function initializeEmergenciesFixedFields($container, canEdit) {
    EditableDatatable.create(`#table-emergencies-fixed-fields`, {
        route: Routing.generate('settings_fixed_field_api', {entity: `urgence`}),
        mode: canEdit ? MODE_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        ordering: false,
        paging: false,
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

function changeReceiverInput($checkbox) {
    const isChecked = $checkbox.is(':checked');
    const $inputReceiver = $checkbox.closest('.modal-body').find('select[name=defaultReceiver]');

    $inputReceiver.attr('disabled', isChecked);
}
