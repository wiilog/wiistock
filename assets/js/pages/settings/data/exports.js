import Routing from '../../../../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
import Form from '@app/form';
import Flash from '@app/flash';

const EXPORT_UNIQUE = `exportUnique`;
const EXPORT_SCHEDULED = `exportScheduled`;

const ENTITY_REFERENCE = "references";
const ENTITY_ARTICLE = "articles";
const ENTITY_TRANSPORT_ROUNDS = "transportRounds";
const ENTITY_ARRIVALS = "arrivals";

global.displayNewExportModal = displayNewExportModal;
global.toggleFrequencyInput = toggleFrequencyInput;
global.selectHourlyFrequencyIntervalType = selectHourlyFrequencyIntervalType;
global.destinationExportChange = destinationExportChange;

let $modalNewExport = $("#modalNewExport");
let $submitNewExport = $("#submitNewExport");

let tableExport = null;

$(document).ready(() => {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_EXPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, `json`);

    tableExport = initDataTable(`tableExport`, {
        processing: true,
        serverSide: true,
        ajax: {
            url: Routing.generate(`settings_export_api`),
            type: `POST`
        },
        columns: [
            {data: `actions`, title: ``, orderable: false, className: `noVis hideOrder`},
            {data: `status`, title: `Statut`},
            {data: `creationDate`, title: `Date de création`},
            {data: `startDate`, title: `Date début`},
            {data: `endDate`, title: `Date fin`},
            {data: `nextRun`, title: `Prochaine exécution`},
            {data: `frequency`, title: `Fréquence`},
            {data: `user`, title: `Utilisateur`},
            {data: `type`, title: `Type`},
            {data: `entity`, title: `Type de données exportées`},
        ],
        rowConfig: {
            needsRowClickAction: true
        },
    });

    Form.create($modalNewExport).onSubmit(data => {
        wrapLoadingOnActionButton($submitNewExport, () => {
            data = data.asObject();
            if(data.exportTypeContainer === EXPORT_UNIQUE) {
                if (data.entityToExport === ENTITY_REFERENCE) {
                    window.open(Routing.generate(`settings_export_references`));
                } else if (data.entityToExport === ENTITY_ARTICLE) {
                    window.open(Routing.generate(`settings_export_articles`));
                } else if (data.entityToExport === ENTITY_TRANSPORT_ROUNDS) {
                    const dateMin = $modalNewExport.find(`[name=dateMin]`).val();
                    const dateMax = $modalNewExport.find(`[name=dateMax]`).val();

                    if(!dateMin || !dateMax || dateMin === `` || dateMax === ``) {
                        Flash.add(`danger`, `Les bornes de dates sont requise pour les exports de tournées`);
                        return Promise.resolve();
                    }

                    window.open(Routing.generate(`settings_export_round`, {
                        dateMin,
                        dateMax,
                    }));
                } else if (data.entityToExport === ENTITY_ARRIVALS) {

                }

                return new Promise((resolve) => {
                    $(window).on('focus.focusAfterExport', function() {
                        $modalNewExport.modal(`hide`);
                        tableExport.ajax.reload();
                        $(window).off('focus.focusAfterExport');
                        resolve();
                    });
                })
            } else {
                const params = JSON.stringify(data.asObject());
                //TODO: enregistrer

                // TODO remove  ?
                return Promise.resolve();
            }

        });
    });
});

function getExportType() {
    return $(`input[name="exportTypeContainer"]:checked`).val();
}

function getExportedEntity() {
    return $(`input[name="entityToExport"]:checked`).val();
}

function displayNewExportModal(){
    $modalNewExport.modal(`show`);

    $.get(Routing.generate('new_export_modal', true), function(resp){
        $modalNewExport.find('.modal-body').html(resp);

        initDateTimePicker('[name=dateMin], [name=dateMax]');

        $('.export-type-container').on('change', function(){
            $('.unique-export-container').toggleClass('d-none');
            $('.scheduled-export-container').toggleClass('d-none');

            $('.frequencies').find('input[type=radio]').each(function(){
                $(this).prop('checked', false);
            });

            const $globalFrequencyContainer = $('.frequency-content');
            $globalFrequencyContainer.addClass('d-none');

            $globalFrequencyContainer
                .find('input.frequency-data, select.frequency-data')
                .removeClass('data')
                .removeClass('needed');
        });

        $('.export-references').on('click', function(){
            $('.ref-articles-sentence').removeClass('d-none');
            $('.date-limit').addClass('d-none');
        });

        $('.export-articles').on('click', function(){
            $('.ref-articles-sentence').removeClass('d-none');
            $('.date-limit').addClass('d-none');
        });

        $('.export-transport-rounds').on('click', function(){
            $('.ref-articles-sentence').addClass('d-none');
            $('.date-limit').removeClass('d-none');
        });

        $('.export-arrivals').on('click', function(){
            $('.ref-articles-sentence').addClass('d-none');
            $('.date-limit').removeClass('d-none');
        });

        Select2Old.user($('.select2-user'));
        Select2Old.initFree($('.select2-free'));
    });

    $modalNewExport.modal('show');

   // $submitNewExport.on(`click`, function() {

    //     });
}

function toggleFrequencyInput($input) {
    const $modal = $input.closest('.modal');
    const $globalFrequencyContainer = $modal.find('.frequency-content');
    const inputName = $input.attr('name');
    const $inputChecked = $modal.find(`[name="${inputName}"]:checked`);
    const inputCheckedVal = $inputChecked.val();
    const $frequencyInput = $modal.find('[name="frequency"]');

    $frequencyInput.val(inputCheckedVal);

    $globalFrequencyContainer.addClass('d-none');
    $globalFrequencyContainer.find('.frequency').addClass('d-none');
    $globalFrequencyContainer
        .find('input.frequency-data, select.frequency-data')
        .removeClass('data')
        .removeClass('needed');
    $globalFrequencyContainer.find('.is-invalid').removeClass('is-invalid');

    if(inputCheckedVal) {
        $globalFrequencyContainer.removeClass('d-none');
        const $frequencyContainer = $globalFrequencyContainer.find(`.frequency.${inputCheckedVal}`);
        $frequencyContainer.removeClass('d-none');
        $frequencyContainer
            .find('input.frequency-data, select.frequency-data')
            .addClass('needed')
            .addClass('data');

        $frequencyContainer.find('input[type="date"]').each(function() {
            const $input = $(this);
            $input.attr('type', 'text');
            initDateTimePicker({dateInputs: $input, minDate: true, value: $input.val()});
        });
    }
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

function destinationExportChange(element){
    $('.export-email-destination').toggleClass('d-none');
    $('.export-sftp-destination').toggleClass('d-none');
}
