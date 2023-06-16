let tableMvt;

$(function () {
    $('.select2').select2();
    const $modalNewMvtTraca = $('#modalNewMvtTraca');
    $modalNewMvtTraca.find('.list-multiple').select2();

    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);
    initDateTimePicker('#datetime', DATE_FORMATS_TO_DISPLAY[format] + ` HH:mm`, {setTodayDate: true});
    initDatePickers();
    Select2Old.init($('#emplacement'), 'Emplacements');

    initTrackingMovementTable($(`#tableMvts`).data(`initial-visible`));

    if(!$(`#filterArticle`).exists()) {
        const filters = JSON.parse($(`#trackingMovementFilters`).val())
        displayFiltersSup(filters, true);
    }

    Select2Old.user(Translation.of('Traçabilité', 'Mouvements', 'Opérateurs', false));
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, Translation.of( 'Traçabilité', 'Général', 'Emplacement', false), 3);

    initNewModal($modalNewMvtTraca);

    $(document).on(`keypress`, `[data-fill-location]`, function(event) {
        if(event.code === `Enter`) {
            loadLULocation($(this));
        }
    });

    $(document).on(`focusout`, `[data-fill-location]`, function () {
        loadLULocation($(this));
    });

    $(document).on(`keypress`, `[data-fill-quantity]`, function(event) {
        if(event.code === `Enter`) {
            loadLUQuantity($(this));
        }
    });

    $(document).on(`focusout`, `[data-fill-quantity]`, function () {
        loadLUQuantity($(this));
    });
});

function loadLUQuantity($selector) {
    const $modalNewMvtTraca = $('#modalNewMvtTraca');
    const $quantity = $modalNewMvtTraca.find(`[name="quantity"]`);
    const code = $selector.val();

    AJAX.route(`GET`, `tracking_movement_logistic_unit_quantity`, {code})
        .json()
        .then(response => {
            $modalNewMvtTraca.find(`#submitNewMvtTraca`).prop(`disabled`, response.error);
            if (response.error) {
                Flash.add(`danger`, response.error);
            }

            if (response.quantity) {
                $quantity.val(response.quantity);
            } else {
                $quantity.empty();
                $quantity.val(1);
            }

            $quantity.prop(`disabled`, !!response.quantity).trigger(`change`);
        });
}

function loadLULocation($input) {
    const $modalNewMvtTraca = $('#modalNewMvtTraca');
    const code = $input.val();

    AJAX.route(`GET`, `tracking_movement_logistic_unit_location`, {code})
        .json()
        .then(response => {
            $modalNewMvtTraca.find(`#submitNewMvtTraca`).prop(`disabled`, response.error);
            if (response.error) {
                Flash.add(`danger`, response.error);
            }

            const $location = $modalNewMvtTraca.find(`[name="emplacement"]`);
            if (response.location) {
                $location.append(new Option(response.location.label, response.location.id, true, true))
            } else {
                $location.val(null);
            }

            $location.prop(`disabled`, !!response.location).trigger(`change`);
        });
}

function initTrackingMovementTable(columns) {
    let trackingMovementTableConfig = {
        responsive: true,
        serverSide: true,
        processing: true,
        order: [['date', "desc"]],
        ajax: {
            url: Routing.generate('tracking_movement_api', true),
            type: "POST",
            data: {
                article: $(`#filterArticle`).val(),
            }
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

    tableMvt = initDataTable('tableMvts', trackingMovementTableConfig);
    initPageModal(tableMvt);
}


$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableMvt.column('date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

        return ((dateMin === "" && dateMax === "")
            || (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
            || (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
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

    modalNewMvtTraca.on(`shown.bs.modal`, function() {
        fillDatePickers('[name="datetime"]', 'YYYY-MM-DD', true);
    })

    InitModal(
        modalNewMvtTraca,
        submitNewMvtTraca,
        urlNewMvtTraca,
        {
            tables: [tableMvt],
            keepModal: Number($('#redirectAfterTrackingMovementCreation').val()),
            keepForm: true,
            confirmMessage: $modal => {
                return new Promise((resolve, reject) => {
                    const pack = $modal.find(`[name="pack"]`).val();
                    const type = $modal.find(`[name="type"] option:selected`).text().trim();

                    if(type !== `prise` || !pack) {
                        return resolve(true);
                    }

                    AJAX.route(`GET`, `tracking_movement_is_in_lu`, {barcode: pack})
                        .json()
                        .then(result => {
                            if(result.in_logistic_unit) {
                                Modal.confirm({
                                    title: `Article dans unité logistique`,
                                    message: `L'article ${pack} sera enlevé de l'unité logistique ${result.logistic_unit}`,
                                    validateButton: {
                                        color: 'success',
                                        label: 'Continuer',
                                        click: () => resolve(true)
                                    },
                                    cancelled: () => resolve(false),
                                });
                            } else {
                                resolve(true)
                            }
                        })
                })
            },
            success: ({success, trackingMovementsCounter, group}) => {
                if (group) {
                    displayConfirmationModal(group);
                } else {
                    displayOnSuccessCreation(success, trackingMovementsCounter);
                    clearModal($('#modalNewMvtTraca'));

                    fillDatePickers('.free-field-date');
                    fillDatePickers('.free-field-datetime', 'YYYY-MM-DD', true);
                }
            }
        });
}

function initNewModal($modal) {
    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    Select2Old.user($operatorSelect, 'Opérateur');

    // Init mouvement fields if already loaded
    const $moreMassMvtContainer = $modal.find('.form-mass-mvt-container');
    if ($moreMassMvtContainer.length > 0) {
        const $emplacementPrise = $moreMassMvtContainer.find('.ajax-autocomplete-location[name="emplacement-prise"]');
        const $emplacementDepose = $moreMassMvtContainer.find('.ajax-autocomplete-location[name="emplacement-depose"]');
        const $pack = $moreMassMvtContainer.find('.select2-free[name="pack"]');
        Select2Old.location($emplacementPrise, {autoSelect: true, $nextField: $pack});
        Select2Old.initFree($pack);
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

    fillDatePickers('.free-field-date');
    fillDatePickers('.free-field-datetime', 'YYYY-MM-DD', true);

}

function switchMvtCreationType($input) {
    let pathToGetAppropriateHtml = Routing.generate("mouvement_traca_get_appropriate_html", true);
    let paramsToGetAppropriateHtml = $input.val();

    $(`#submitNewMvtTraca`).prop(`disabled`, false);

    $.post(pathToGetAppropriateHtml, JSON.stringify(paramsToGetAppropriateHtml), function (response) {
        if (response) {
            const $modal = $input.closest('.modal');
            $modal.find('.more-body-new-mvt-traca').html(response.modalBody);
            $modal.find('.new-mvt-common-body').removeClass('d-none');
            $modal.find('.more-body-new-mvt-traca').removeClass('d-none');
            Select2Old.location($modal.find('.ajax-autocomplete-location'));
            Select2Old.initFree($modal.find('.select2-free'));

            const $emptyRound = $modal.find('input[name=empty-round]');
            if ($input.find(':selected').text().trim() === $emptyRound.val()) {
                const $packInput = $modal.find('select[name=pack]');
                $modal.find('input[name=quantity]').closest('div.form-group').addClass('d-none');
                $packInput.val('passageavide');
                $packInput.prop('disabled', true);
            }

            $modal.find(`select[name=pack]`).select2({
                tags: true,
                tokenSeparators: [" "],
                tokenizer: (input, selection, callback) => {
                    return Wiistock.Select2.tokenizer(input, selection, callback, ' ');
                },
            });
        }
    });
}

/**
 * Used in mouvement_association/index.html.twig
 */
function clearURL() {
    window.history.pushState({}, document.title, `${window.location.pathname}`);
}

function displayConfirmationModal(group) {
    displayAlertModal(
        undefined,
        $('<div/>', {
            class: 'text-center',
            html: `Cette unité logistique est présente dans le groupe <strong>${group}</strong>. Confirmer le mouvement l\'enlèvera du groupe. <br>Voulez-vous continuer ?`
        }),
        [
            {
                class: 'btn btn-outline-secondary m-0',
                text: 'Non',
                action: ($modal) => {
                    $('input[name="forced"]').val("0");
                    $modal.modal('hide');
                }
            },
            {
                class: 'btn btn-success m-0',
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
                    ? Translation.of('Traçabilité', 'Mouvements', 'Mouvements créés avec succès.', false)
                    : Translation.of('Traçabilité', 'Mouvements', 'Mouvement créé avec succès.', false))
                : Translation.of('Traçabilité', 'Mouvements', 'Aucun mouvement créé.', false)
        }),
        [
            {
                class: 'btn btn-success m-0',
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

