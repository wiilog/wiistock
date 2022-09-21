global.displayNewExportModal = displayNewExportModal;
global.toggleFrequencyInput = toggleFrequencyInput;
global.selectHourlyFrequencyIntervalType = selectHourlyFrequencyIntervalType;
global.destinationExportChange = destinationExportChange;

let $modalNewExport = $("#modalNewExport");
let $submitNewExport = $("#submitNewExport");

$(document).ready(() => {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_EXPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, `json`);

    const tableExport = initDataTable(`tableExport`, {
        processing: true,
        serverSide: true,
        ajax: {
            url: Routing.generate(`settings_export_api`),
            type: `POST`
        },
        columns: [
            {data: `actions`, title: ``, orderable: false, className: `noVis`},
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
});

function displayNewExportModal(){
    clearModal($modalNewExport);
    InitModal($modalNewExport, $submitNewExport, ''); //TODO faire la route de validation de la modale

    $.get(Routing.generate('new_export_modal', true), function(resp){
        $modalNewExport.find('.modal-body').html(resp);

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
