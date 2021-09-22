let tableMvt;
let quillNew;

$(function () {
    $('.select2').select2();
    const $modalNewMvtTraca = $('#modalNewMvtTraca');
    $modalNewMvtTraca.find('.list-multiple').select2();

    initDateTimePicker();
    Select2Old.init($('#statut'), 'Types');
    Select2Old.init($('#emplacement'), 'Emplacements');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_TRACA);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    Select2Old.user('Opérateurs');
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    initNewModal($modalNewMvtTraca);

    $.post(Routing.generate('tracking_movement_api_columns'))
        .then((columns) => {
            let config = {
                responsive: true,
                serverSide: true,
                processing: true,
                order: [[3, "desc"]],
                ajax: {
                    "url": Routing.generate('tracking_movement_api', true),
                    "type": "POST",
                },
                drawConfig: {
                    needsSearchOverride: true,
                },
                rowConfig: {
                    needsRowClickAction: true
                },
                columns,
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableMvts'
                }
            };

            tableMvt = initDataTable('tableMvts', config);
            initPageModal(tableMvt);
        });
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableMvt.column('date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

        return ((dateMin == "" && dateMax == "")
            || (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
            || (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
            || (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax)));
    }
);


function initPageModal(tableMvt) {
    let $modalEditMvtTraca = $("#modalEditMvtTraca");
    let $submitEditMvtTraca = $("#submitEditMvtTraca");
    let urlEditMvtTraca = Routing.generate('mvt_traca_edit', true);
    InitModal($modalEditMvtTraca, $submitEditMvtTraca, urlEditMvtTraca, {tables: [tableMvt]});

    let $modalDeleteMvtTraca = $('#modalDeleteMvtTraca');
    let $submitDeleteMvtTraca = $('#submitDeleteMvtTraca');
    let urlDeleteArrivage = Routing.generate('mvt_traca_delete', true);
    InitModal($modalDeleteMvtTraca, $submitDeleteMvtTraca, urlDeleteArrivage, {tables: [tableMvt]});

    let modalNewMvtTraca = $("#modalNewMvtTraca");
    let submitNewMvtTraca = $("#submitNewMvtTraca");
    let urlNewMvtTraca = Routing.generate('mvt_traca_new', true);
    InitModal(
        modalNewMvtTraca,
        submitNewMvtTraca,
        urlNewMvtTraca,
        {
            tables: [tableMvt],
            keepModal: !Number($('#redirectAfterTrackingMovementCreation').val()),
            keepForm: true,
            success: ({success, trackingMovementsCounter, group}) => {
                if (group) {
                    displayConfirmationModal(group);
                } else {
                    displayOnSuccessCreation(success, trackingMovementsCounter);
                    clearModal($('#modalNewMvtTraca'));
                }
            }
        });
}

function initNewModal($modal) {
    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    Select2Old.user($operatorSelect, 'Opérateur');

    if (!quillNew) {
        quillNew = initEditor('#' + $modal.attr('id') + ' .editor-container-new');
    }

    // Init mouvement fields if already loaded
    const $moreMassMvtContainer = $modal.find('.form-mass-mvt-container');
    if ($moreMassMvtContainer.length > 0) {
        const $emplacementPrise = $moreMassMvtContainer.find('.ajax-autocomplete-location[name="emplacement-prise"]');
        const $emplacementDepose = $moreMassMvtContainer.find('.ajax-autocomplete-location[name="emplacement-depose"]');
        const $colis = $moreMassMvtContainer.find('.select2-free[name="colis"]');
        Select2Old.location($emplacementPrise, {autoSelect: true, $nextField: $colis});
        Select2Old.initFree($colis);
        Select2Old.location($emplacementDepose, {autoSelect: true});
    }
}

function resetNewModal($modal) {
    // date
    const date = moment().format();
    $modal.find('.datetime').val(date.slice(0, 16));

    // operator
    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    const $loggedUserInput = $modal.find('input[hidden][name="logged-user"]');
    let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
    $operatorSelect
        .val(null)
        .trigger('change')
        .append(option)
        .trigger('change');

    $modal.find('.more-body-new-mvt-traca').empty();

    // focus emplacementPrise if mass mouvement form is already loaded
    const $emplacementPrise = $modal.find('.ajax-autocomplete-location[name="emplacement-prise"]');
    if ($modal.length > 0) {
        setTimeout(() => {
            $emplacementPrise.select2('open');
        }, 400);
    }
}

function switchMvtCreationType($input) {
    let pathToGetAppropriateHtml = Routing.generate("mouvement_traca_get_appropriate_html", true);
    let paramsToGetAppropriateHtml = $input.val();
    $.post(pathToGetAppropriateHtml, JSON.stringify(paramsToGetAppropriateHtml), function (response) {
        if (response) {
            const $modal = $input.closest('.modal');
            $modal.find('.more-body-new-mvt-traca').html(response.modalBody);
            $modal.find('.new-mvt-common-body').removeClass('d-none');
            $modal.find('.more-body-new-mvt-traca').removeClass('d-none');
            Select2Old.location($modal.find('.ajax-autocomplete-location'));
            Select2Old.initFree($modal.find('.select2-free'));

            const $emptyRound = $modal.find('input[name=empty-round]');
            if($input.find(':selected').text().trim() === $emptyRound.val()) {
                const $packInput = $modal.find('input[name=colis]');
                $packInput.val('passageavide');
                $packInput.prop('disabled', true);
            }
        }
    });
}

/**
 * Used in mouvement_traca/index.html.twig
 */
function clearURL() {
    window.history.pushState({}, document.title, `${window.location.pathname}`);
}

function displayConfirmationModal(group) {
    displayAlertModal(
        undefined,
        $('<div/>', {
            class: 'text-center',
            html: `Ce colis est présent dans le groupe <strong>${group}</strong>. Confirmer le mouvement l\'enlèvera du groupe. <br>Voulez-vous continuer ?`
        }),
        [
            {
                class: 'btn btn-secondary m-0',
                text: 'Non',
                action: ($modal) => {
                    $('input[name="forced"]').val("0");
                    $modal.modal('hide');
                }
            },
            {
                class: 'btn btn-primary m-0',
                text: 'Oui',
                action: ($modal) => {
                    $('input[name="forced"]').val(1);
                    $('#submitNewMvtTraca').click();
                    $modal.modal('hide');
                }
            },
        ],
        'warning'
    );
}

function displayOnSuccessCreation(success, trackingMovementsCounter) {
    displayAlertModal(
        undefined,
        $('<div/>', {
            class: 'text-center',
            text: trackingMovementsCounter > 0
                ? (trackingMovementsCounter > 1
                    ? 'Mouvements créés avec succès.'
                    : 'Mouvement créé avec succès.')
                : 'Aucun mouvement créé.'
        }),
        [
            {
                class: 'btn btn-primary m-0',
                text: 'Continuer',
                action: ($modal) => {
                    $modal.modal('hide');
                    $('input[name="forced"]').val("0");
                }
            }
        ],
        success ? 'success' : 'error'
    );
}

