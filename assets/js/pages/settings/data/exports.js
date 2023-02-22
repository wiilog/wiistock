import Routing from '../../../../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
import Modal from "@app/modal";

import Form from '@app/form';
import Flash from '@app/flash';
import {onSelectAll, toggleFrequencyInput} from '@app/pages/settings/utils';
import AJAX, {POST} from "@app/ajax";

const EXPORT_UNIQUE = `unique`;
const EXPORT_SCHEDULED = `scheduled`;

const ENTITY_REFERENCE = "reference";
const ENTITY_ARTICLE = "article";
const ENTITY_TRANSPORT_ROUNDS = "tournee";
const ENTITY_ARRIVALS = "arrivage";

global.displayExportModal = displayExportModal;
global.selectHourlyFrequencyIntervalType = selectHourlyFrequencyIntervalType;
global.destinationExportChange = destinationExportChange;
global.forceExport = forceExport;
global.cancelExport = cancelExport;

let tableExport = null;

$(document).ready(() => {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_EXPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, `json`);

    let $modalNewExport = $("#modalExport");
    createForm($modalNewExport);

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
            {data: `nextExecution`, title: `Prochaine exécution`},
            {data: `frequency`, title: `Fréquence`},
            {data: `user`, title: `Utilisateur`},
            {data: `type`, title: `Type`},
            {data: `entity`, title: `Type de données exportées`},
        ],
        rowConfig: {
            needsRowClickAction: true
        },
    });

});

function displayExportModal(exportId) {
    let $modal = $("#modalExport");

    $modal.modal(`show`);
    const params = exportId
        ? {export: exportId}
        : {};
    const title = exportId
        ? "Modifier la planification d'un export"
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

    $.get(Routing.generate('export_template', params, true), function(resp){
        $modal.find('.modal-body').html(resp);
        onFormEntityChange();
        onFormTypeChange(false);
        onPeriodIntervalChange($modal);

        const $checkedFrequency = $modal.find('[name=frequency]:checked');
        if ($checkedFrequency.exists()) {
            toggleFrequencyInput($checkedFrequency);
        }

        const $dateInput = $modal.find('[name=dateMin], [name=dateMax], [name=scheduledDateMin], [name=scheduledDateMax], [name=articleDateMin], [name=articleDateMax]');
        initDateTimePicker($dateInput);

        Select2Old.user($modal.find('.select2-user'));
        Select2Old.initFree($modal.find('.select2-free'));
        $modal.find('select[name=columnToExport]').select2({closeOnSelect: false});
        $modal.find('select[name=referenceTypes]').select2({closeOnSelect: false});
        $modal.find('select[name=statuses]').select2({closeOnSelect: false});
        $modal.find('select[name=suppliers]').select2({closeOnSelect: false});
        $modal.find('.select-all-options').on('click', onSelectAll);

        if($modal.find('input[name=destinationType]:checked').hasClass('export-by-sftp')){
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

function forceExport(exportId) {
    AJAX.route(POST, 'settings_export_force', {export: exportId})
        .json()
        .then(() => {
            tableExport.ajax.reload();
        });
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
        .addProcessor((data, errors, $form) => {
            const destinationType = Number(data.get('destinationType'));
            const recipientUsers = data.get('recipientUsers');
            const recipientEmails = data.get('recipientEmails');
            const isEmailExport = destinationType === 1;
            if (isEmailExport && !recipientUsers && !recipientEmails) {
                errors.push({
                    elements: [$form.find('[name=recipientUsers]'), $form.find('[name=recipientEmails]')],
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
                        console.log(referenceTypes, statuses, suppliers);

                        if(!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports d'articles`);
                            return Promise.resolve();
                        } else if(referenceTypes.length === 0){
                            Flash.add(`danger`, `Veuillez choisir au moins un type de référence`);
                            return Promise.resolve();
                        } else if (statuses.length === 0) {
                            Flash.add(`danger`, `Veuillez choisir au moins un statut`);
                            return Promise.resolve();
                        } else if (suppliers.length === 0) {
                            Flash.add(`danger`, `Veuillez choisir au moins un fournisseur`);
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

                        if(!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports de tournées`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_round`, {
                            dateMin,
                            dateMax,
                        }));
                    } else if (content.entityToExport === ENTITY_ARRIVALS) {
                        const dateMin = $modal.find(`[name=dateMin]`).val();
                        const dateMax = $modal.find(`[name=dateMax]`).val();
                        const columnToExport = $modal.find(`[name=columnToExport]`).val();

                        if(!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                            Flash.add(`danger`, `Les bornes de dates sont requises pour les exports de tournées`);
                            return Promise.resolve();
                        } else if(columnToExport.length === 0){
                            Flash.add(`danger`, `Veuillez choisir des colonnes à exporter`);
                            return Promise.resolve();
                        }

                        window.open(Routing.generate(`settings_export_arrival`, {
                            dateMin,
                            dateMax,
                            columnToExport
                        }));
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

function onFormEntityChange() {
    let $modal = $("#modalExport");
    const selectedEntity = $modal.find('[name=entityToExport]:checked').val();
    const $articlesSentence = $modal.find('.articles-sentence');
    const $referencesSentence = $modal.find('.references-sentence');
    const $articleFields = $modal.find('.article-fields');
    const $columnToExportContainer = $modal.find('.column-to-export');
    const $columnToExport = $columnToExportContainer.find('select');
    const $periodInterval = $modal.find('.period-interval');
    const $dateLimit = $modal.find('.date-limit');
    const $scheduledArticleDates = $modal.find('.scheduled-article-dates');

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
        case ENTITY_TRANSPORT_ROUNDS:
            $dateLimit.removeClass('d-none');
            $periodInterval.removeClass('d-none');
            break;
        case ENTITY_ARRIVALS:
            $dateLimit.removeClass('d-none');
            $columnToExportContainer.removeClass('d-none');
            $columnToExport.addClass('needed');
            $periodInterval.removeClass('d-none');
            break;
        default:
            break;
    }
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
            method: POST,
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
