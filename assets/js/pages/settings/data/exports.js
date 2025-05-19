import Modal from "@app/modal";
import Routing from '@app/fos-routing';

import Form from '@app/form';
import Flash from '@app/flash';
import {onSelectAll, toggleFrequencyInput} from '@app/pages/settings/utils';
import AJAX, {PATCH, POST} from "@app/ajax";
import moment from "moment";
import {getUserFiltersByPage} from '@app/utils';
import {initDataTable} from "@app/datatable";

const EXPORT_UNIQUE = `unique`;
const EXPORT_SCHEDULED = `scheduled`;

const ENTITY_REFERENCE = "reference";
const ENTITY_ARTICLE = "article";
const ENTITY_TRANSPORT_ROUNDS = "tournee";
const ENTITY_ARRIVALS = "arrivage";
const ENTITY_REF_LOCATION = "reference_emplacement";
const ENTITY_DISPATCH = "dispatch";
const ENTITY_PRODUCTION = "production";
const ENTITY_TRACKING_MOVEMENT = "tracking_movement";
const ENTITY_PACK = "pack";
const ENTITY_RECEIPT_ASSOCIATION = "receipt_association";
const ENTITY_DISPUTE = "dispute";
const ENTITY_TRUCK_ARRIVAL = "truck_arrival";
const ENTITY_EMERGENCY = "emergency"

global.displayExportModal = displayExportModal;
global.selectHourlyFrequencyIntervalType = selectHourlyFrequencyIntervalType;
global.destinationExportChange = destinationExportChange;
global.cancelExport = cancelExport;

let tableExport = null;

export function initializeExports() {
    initDateTimePicker('#dateMin, #dateMax');
    getUserFiltersByPage(PAGE_EXPORT);
    createForm();

    tableExport = initDataTable(`tableExport`, {
        processing: true,
        serverSide: true,
        ajax: {
            url: Routing.generate(`settings_export_api`),
            type: `POST`
        },
        order: [['beganAt', "desc"]],
        columns: [
            {data: `actions`, title: ``, orderable: false, className: `noVis hideOrder`},
            {data: `status`, title: `Statut`},
            {data: `createdAt`, title: `Date de création`},
            {data: `beganAt`, title: `Date début`},
            {data: `endedAt`, title: `Date fin`},
            {data: `nextExecution`, title: `Prochaine exécution`, orderable: false},
            {data: `frequency`, title: `Fréquence`},
            {data: `user`, title: `Utilisateur`},
            {data: `type`, title: `Type`},
            {data: `entity`, title: `Type de données exportées`},
        ],
        rowConfig: {
            needsRowClickAction: true
        },
    });
}

function displayExportModal(exportId, isDuplication = false) {
    let $modal = $("#modalExport");
    $modal.modal(`show`);
    const params = exportId
        ? {export: exportId}
        : {};
    const title = exportId
        ? isDuplication
            ? "Dupliquer la planification d'un export"
            : "Modifier la planification d'un export"
        : "Nouvel export";

    $modal.find('.modal-title')
        .text(title);

    $modal.find('.modal-body')
        .html(`
            <div class="row justify-content-center">
                <div class="col-auto">
                    <div class="spinner-border">
                        <span class="sr-only">Chargement...</span>
                    </div>
                </div>
            </div>
        `);

    $.get(Routing.generate('export_template', params, true), function(resp) {
        $modal.find('.modal-body').html(resp);
        onFormEntityChange();
        onFormTypeChange(false);
        onPeriodIntervalChange($modal);

        if (isDuplication) {
            $modal.find('[name=exportId]').val(null);
        }

        const $checkedFrequency = $modal.find('[name=frequency]:checked');
        if ($checkedFrequency.exists()) {
            toggleFrequencyInput($checkedFrequency);
        }

        const $dateInput = $modal.find('[name=dateMin], [name=dateMax], [name=articleDateMin], [name=articleDateMax]');
        initDateTimePicker($dateInput);

        Select2Old.user($modal.find('.select2-user'));
        Select2Old.initFree($modal.find('.select2-free'));
        $modal.find('select[name=referenceTypes]').select2({closeOnSelect: false});
        $modal.find('select[name=statuses]').select2({closeOnSelect: false});
        $modal.find('select[name=suppliers]').select2({closeOnSelect: false});
        $modal.find('.select-all-options').on('click', onSelectAll);

        if($modal.find('input[name=destinationType]:checked').hasClass('export-by-sftp')) {
            destinationExportChange();
        }
    });

    $modal.modal('show');
}

function selectHourlyFrequencyIntervalType($select) {
    const $selectedOptionValue = $select.find(":selected").val();
    const $frequencyContent = $select.closest('.frequency-content');
    let $frequencyPeriodInput = $frequencyContent.find('input[name="hourly-frequency-interval-period"]');

    if ($selectedOptionValue === 'minutes') {
        $frequencyPeriodInput.attr('max', 30);
    } else if ($selectedOptionValue === 'hours') {
        $frequencyPeriodInput.attr('max', 12);
    }
}

function destinationExportChange(){
    $('.export-email-destination').toggleClass('d-none');
    $('.export-sftp-destination').toggleClass('d-none');
}

function handleExportSaving($modal, table) {
    $modal.modal(`hide`);
    table.ajax.reload();
}

function createForm() {
    const $modal = $("#modalExport");
    Form.create($modal)
        .on('change', '[name=entityToExport]', function() {
            onFormEntityChange();
        })
        .on('change', '[name=type]', function() {
            onFormTypeChange();
        })
        .on('change', '[name=periodInterval]', function() {
            onPeriodIntervalChange($modal);
        })
        .on('change', '[name=scheduled-date-radio]', function() {
            onFormDateTypeChange();
        })
        .addProcessor((data, errors, form) => {
            const destinationType = Number(data.get('destinationType'));
            const recipientUsers = data.get('recipientUsers');
            const recipientEmails = data.get('recipientEmails');
            const isEmailExport = destinationType === 1;
            if (isEmailExport && !recipientUsers && !recipientEmails) {
                errors.push({
                    elements: [form.find('[name=recipientUsers]'), form.find('[name=recipientEmails]')],
                    message: `Vous devez renseigner au moins un utilisateur ou une adresse email destinataire`,
                });
            }
        })
        .onSubmit((data, form) => {
            form.loading(() => {
                if(!data) {
                    return;
                }

                const content = data.asObject();

                if(content.type === EXPORT_UNIQUE) {
                    if (content.entityToExport === ENTITY_REFERENCE) {
                        window.open(Routing.generate(`settings_export_references`));
                    } else if (content.entityToExport === ENTITY_ARTICLE) {
                        const referenceTypes = $modal.find(`[name=referenceTypes]`).val();
                        const statuses = $modal.find(`[name=statuses]`).val();
                        const suppliers = $modal.find(`[name=suppliers`).val();
                        const dateMin = $modal.find(`[name=articleDateMin]`).val();
                        const dateMax = $modal.find(`[name=articleDateMax]`).val();

                        if (dateMin !== '' && dateMax !== '' && moment(dateMin, 'DD/MM/YYYY').isAfter(moment(dateMax, 'DD/MM/YYYY'))) {
                            Flash.add(`danger`, `Les bornes de dates d'entrée de stock sont invalides`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_articles`, {
                            dateMin,
                            dateMax,
                            referenceTypes,
                            statuses,
                            suppliers
                        }));
                    } else if (content.entityToExport === ENTITY_TRANSPORT_ROUNDS) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports de tournées`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_round`, {
                            dateMin,
                            dateMax,
                        }));
                    } else if (content.entityToExport === ENTITY_DISPATCH) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();
                        const columnToExport = $modal.find(`[name=columnToExport]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports d'acheminements`);
                            return Promise.resolve();
                        } else if (columnToExport.length === 0) {
                            Flash.add(`danger`, `Veuillez choisir des colonnes à exporter`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_dispatches`, {
                            dateMin,
                            dateMax,
                            columnToExport,
                        }));
                    } else if (content.entityToExport === ENTITY_TRACKING_MOVEMENT) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();
                        const columnToExport = $modal.find(`[name=columnToExport]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports de mouvements de traçabilité`);
                            return Promise.resolve();
                        } else if (columnToExport.length === 0) {
                            Flash.add(`danger`, `Veuillez choisir des colonnes à exporter`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_tracking_movements`, {
                            dateMin,
                            dateMax,
                            columnToExport,
                        }));
                    } else if (content.entityToExport === ENTITY_PRODUCTION) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports de demandes de production`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_production_requests`, {
                            dateMin,
                            dateMax,
                        }));
                    } else if (content.entityToExport === ENTITY_ARRIVALS) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();
                        const columnToExport = $modal.find(`[name=columnToExport]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports d'arrivages`);
                            return Promise.resolve();
                        } else if (columnToExport.length === 0) {
                            Flash.add(`danger`, `Veuillez choisir des colonnes à exporter`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_arrival`, {
                            dateMin,
                            dateMax,
                            columnToExport
                        }));
                    } else if (content.entityToExport === ENTITY_REF_LOCATION) {
                        window.open(Routing.generate(`settings_export_ref_location`));
                    } else if (content.entityToExport === ENTITY_PACK) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports d'unités logistics`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_packs`, {
                            dateMin,
                            dateMax,
                        }));
                    } else if (content.entityToExport === ENTITY_TRUCK_ARRIVAL) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports d'arrivages de camions`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_truck_arrival`, {
                            dateMin,
                            dateMax,
                        }));
                    } else if (content.entityToExport === ENTITY_RECEIPT_ASSOCIATION) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports d'associations BR`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_receipt_association`, {
                            dateMin,
                            dateMax,
                        }));
                    } else if (content.entityToExport === ENTITY_DISPUTE) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports de litiges`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`dispute_export_csv`, {
                            dateMin,
                            dateMax,
                        }));
                    } else if (content.entityToExport === ENTITY_EMERGENCY) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();

                        if (!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports d'urgence`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_emergency`, {
                            dateMin,
                            dateMax,
                        }));
                    } else {
                        Flash.add(`danger`, `Une erreur est survenue lors de la génération de l'export`);
                    }

                    return new Promise((resolve) => {
                        $(window).on('focus.focusAfterExport', function() {
                            handleExportSaving($modal, tableExport);
                            $(window).off('focus.focusAfterExport');
                            resolve();
                        });
                    })
                }
                else {
                    const exportId = $modal.find('[name=exportId]').val();
                    const route = exportId ? 'settings_edit_export' : 'settings_new_export';
                    const params = exportId ? {export: exportId} : {};

                    if ($modal.find(`[name=scheduledDateMin]`).val()
                        && $modal.find(`[name=scheduledDateMax]`).val()
                        && $modal.find(`[name=scheduledDateMin]`).val() > $modal.find(`[name=scheduledDateMax]`).val()) {
                        Flash.add(`danger`, `Les dates fixes d'entrée en stock sont invalides`);
                        return Promise.resolve();
                    }

                    if ($modal.find(`[name=minus-day]`).val()
                        && $modal.find(`[name=additional-day]`).val()
                        && $modal.find(`[name=minus-day]`).val() < 0 && $modal.find(`[name=additional-day]`).val() < 0 ) {
                        Flash.add(`danger`, `Les dates relative d'entrée en stock sont invalides`);
                        return Promise.resolve();
                    }

                    return AJAX.route(POST, route, params)
                        .json(data)
                        .then(({success}) => {
                            if (success) {
                                handleExportSaving($modal, tableExport);
                            }
                        });
                }
            });
        });
}

function onFormDateTypeChange(){

    let $modal = $("#modalExport");
    const radioArticleChecked = $modal.find('[name=scheduled-date-radio]:checked').val();
    const $scheduledDateMin = $modal.find('[name=scheduledDateMin]');
    const $scheduledDateMax = $modal.find('[name=scheduledDateMax]');
    const $minusDay = $modal.find('[name=minus-day]');
    const $additionalDay = $modal.find('[name=additional-day]');

    switch(radioArticleChecked){
        case "fixed-date":
            $minusDay.val(null);
            $additionalDay.val(null);
            break;
        case "relative-date":
            $scheduledDateMin.val(null);
            $scheduledDateMax.val(null);
            break;
    }
}

function onFormEntityChange() {
    const $modal = $("#modalExport");
    const selectedEntity = $modal.find('[name=entityToExport]:checked').val();
    const $articlesSentence = $modal.find('.articles-sentence');
    const $referencesSentence = $modal.find('.references-sentence');
    const $articleFields = $modal.find('.article-fields');
    const $columnToExportContainer = $modal.find('.column-to-export');
    const $columnToExport = $columnToExportContainer.find('select');
    const $periodInterval = $modal.find('.period-interval');
    const $dateLimit = $modal.find('.date-limit');
    const $scheduledArticleDates = $modal.find('.scheduled-article-dates');
    const $exportableColumns = $modal.find(`[name=exportableColumns]`)
    const $choosenColumnsToExport = $modal.find(`[name=choosenColumnsToExport]`);

    const exportableColumns = JSON.parse($exportableColumns.val());
    const choosenColumnsToExport = JSON.parse($choosenColumnsToExport.val());

    $articlesSentence.addClass('d-none');
    $referencesSentence.addClass('d-none');
    $articleFields.addClass('d-none');
    $columnToExportContainer.addClass('d-none');
    $columnToExport.removeClass('needed');
    $periodInterval.addClass('d-none');
    $scheduledArticleDates.addClass('d-none');
    $dateLimit.addClass('d-none');

    switch (selectedEntity) {
        case ENTITY_REFERENCE:
            $referencesSentence.removeClass('d-none');
            break;
        case ENTITY_ARTICLE:
            $articlesSentence.removeClass('d-none');
            $articleFields.removeClass('d-none');
            $scheduledArticleDates.removeClass('d-none');
            break;
        case ENTITY_DISPATCH:
        case ENTITY_ARRIVALS:
        case ENTITY_TRACKING_MOVEMENT:
            $columnToExportContainer.removeClass('d-none');
            $columnToExport.addClass('needed');
            $dateLimit.removeClass('d-none');
            $periodInterval.removeClass('d-none');

            renderExportableColumns($columnToExport, exportableColumns[selectedEntity], choosenColumnsToExport);
            break;
        case ENTITY_TRANSPORT_ROUNDS:
        case ENTITY_PRODUCTION:
        case ENTITY_EMERGENCY:
        case ENTITY_PACK:
        case ENTITY_TRUCK_ARRIVAL:
        case ENTITY_RECEIPT_ASSOCIATION:
        case ENTITY_DISPUTE:
            $dateLimit.removeClass('d-none');
            $periodInterval.removeClass('d-none');
            break;
        default:
            break;
    }
}

/**
 * Render the exportable columns in the select element
 * @param $columnToExport select element
 * @param entityExportableColumns the columns that can be exported
 * @param choosenColumnsToExport the columns that are already choosen
 */
function renderExportableColumns($columnToExport, entityExportableColumns, choosenColumnsToExport) {
    $columnToExport.empty();

    // Prepare options for chosen columns
    const chosenOptions = choosenColumnsToExport.map(key => {
        const columnLabel = entityExportableColumns[key];
        return `<option value="${key}" selected>${columnLabel}</option>`;
    });

    // Prepare options for remaining columns
    const remainingOptions = Object.entries(entityExportableColumns)
        .filter(([key]) => !choosenColumnsToExport.includes(key))
        .map(([key, label]) => `<option value="${key}">${label}</option>`);

    // Concatenate all options and append to select element
    const allOptions = [...chosenOptions, ...remainingOptions];
    $columnToExport.append(allOptions.join(''));
}

function onFormTypeChange(resetFrequency = true) {
    const $modal = $('#modalExport');
    const exportType = $modal.find('[name=type]:checked').val()
    $modal.find('.unique-export-container').toggleClass('d-none', exportType !== EXPORT_UNIQUE);
    $modal.find('.scheduled-export-container').toggleClass('d-none', exportType !== EXPORT_SCHEDULED);
    $modal.find('.article-date-limit').toggleClass('d-none', exportType !== EXPORT_UNIQUE);

    if (resetFrequency) {
        $modal.find('.frequencies').find('input[type=radio]').each(function () {
            $(this).prop('checked', false);
        });
    }

    const $globalFrequencyContainer = $modal.find('.frequency-content');
    $globalFrequencyContainer.addClass('d-none');

    $globalFrequencyContainer
        .find('input.frequency-data, select.frequency-data')
        .removeClass('data')
        .removeClass('needed');
}

function cancelExport(exportId) {
    Modal.confirm({
        ajax: {
            method: PATCH,
            route: 'settings_export_cancel',
            params: {
                export: exportId
            },
        },
        message: 'Voulez-vous réellement supprimer la planification de cet export ?',
        title: 'Supprimer la planification de cet export',
        validateButton: {
            color: 'danger',
            label: 'Supprimer',
        },
        cancelButton: {
            label: 'Fermer',
        },
        table: tableExport,
    });
}

function onPeriodIntervalChange($modal) {
    let $period = $modal.find('[name=period]');
    let $periodInterval = $modal.find('[name=periodInterval]');
    switch ($periodInterval.val()) {
        case 'day':
            $period.html(
                '<option value="current" selected>en cours (jour J)</option>' +
                '<option value="previous">dernier (jour J-1)</option>'
            );
            break;
        case 'week':
            $period.html(
                '<option value="current" selected>en cours (semaine S)</option>' +
                '<option value="previous">dernière (semaine S-1)</option>'
            );
            break;
        case 'month':
            $period.html(
                '<option value="current" selected>en cours (mois M)</option>' +
                '<option value="current_3" selected>en cours et les 3 précédents (mois M-3 à M)</option>' +
                '<option value="previous">dernier (mois M-1)</option>'
            );
            break;
        case 'year':
            $period.html(
                '<option value="current" selected>en cours (année A)</option>' +
                '<option value="previous">dernière (année A-1)</option>'
            );
            break;
        default:
            break;
    }

}
