import Routing from '@app/fos-routing';
import AJAX, {GET, POST} from '@app/ajax';
import Flash from '@app/flash';
import Modal from '@app/modal';
import Camera from '@app/camera';
import moment from 'moment';
import Form from '@app/form';
import {initDataTable, initSearchDate} from "@app/datatable";

let tableMvt;

global.resetNewModal = resetNewModal;
global.switchMvtCreationType = switchMvtCreationType;
global.clearURL = clearURL;
global.toggleDateInput = toggleDateInput;

$(function () {
    const $modalNewMvtTraca = $('#modalNewMvtTraca');

    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);
    initDateTimePicker('#datetime', DATE_FORMATS_TO_DISPLAY[format] + ` HH:mm`, {setTodayDate: true});
    initDatePickers();
    Select2Old.init($('#emplacement'), 'Emplacements');

    initTrackingMovementTable($(`#tableMvts`).data(`initial-visible`));

    if (!$(`#filterArticle`).exists()) {
        const filters = JSON.parse($(`[name="trackingMovementFilters"]`).val())
        displayFiltersSup(filters, true);
    }
    initNewModal($modalNewMvtTraca);

    $(document).on(`keypress`, `[data-fill-location]`, function (event) {
        if (event.code === `Enter`) {
            loadLULocation($(this));
        }
    });

    $(document).on(`focusout`, `[data-fill-location]`, function () {
        loadLULocation($(this));
    });

    $(document).on(`keypress`, `[data-fill-quantity]`, function (event) {
        if (event.code === `Enter`) {
            loadLUQuantity($(this));
        }
    });

    $(document).on(`focusout`, `[data-fill-quantity]`, function () {
        loadLUQuantity($(this));
    });


    initSearchDate(tableMvt)
});

function loadLUQuantity($selector) {
    const $modalNewMvtTraca = $('#modalNewMvtTraca');
    const $quantity = $modalNewMvtTraca.find(`[name="quantity"]`);
    const code = $selector.val();

    AJAX.route(GET, `tracking_movement_logistic_unit_quantity`, {code})
        .json()
        .then(response => {
            $modalNewMvtTraca.find(`[type="submit"]`).prop(`disabled`, response.error);
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

    AJAX.route(GET, `tracking_movement_logistic_unit_location`, {code})
        .json()
        .then(response => {
            $modalNewMvtTraca.find(`[type="submit"]`).prop(`disabled`, response.error);
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
        info: false,
        order: [['date', "desc"]],
        ajax: {
            url: Routing.generate('tracking_movement_api', true),
            type: "POST",
            data: {
                article: $(`#filterArticle`).val(),
            }
        },
        rowConfig: {
            needsRowClickAction: true
        },
        columns,
    };

    tableMvt = initDataTable('tableMvts', trackingMovementTableConfig);
    initPageModals(tableMvt);
}

function initPageModals(tableMvt) {
    let $modalEditMvtTraca = $("#modalEditMvtTraca");
    Form
        .create($modalEditMvtTraca, {clearOnOpen: false})
        .onOpen(function (event) {
            const trackingMovement = $(event.relatedTarget).data('id');
            clearModal($modalEditMvtTraca);
            Modal.load('tracking_movement_api_edit',
                {trackingMovement},
                $modalEditMvtTraca,
                $modalEditMvtTraca.find(`.modal-body`),
                {
                    onOpen: () => {
                        Camera
                            .init(
                                $modalEditMvtTraca.find(`.take-picture-modal-button`),
                                $modalEditMvtTraca.find(`[name="files[]"]`)
                            );
                    }
                })
            initDatePickers();
        })
        .submitTo(
            POST,
            'mvt_traca_edit',
            {
                tables: [tableMvt]
            }
        );

    $(document).on('click', '.delete-tracability-movement', function (event) {
        const trackingMovement = $(event.currentTarget).data('id');
        Modal.confirm({
            ajax: {
                method: POST,
                route: 'tracking-movement_delete',
                params: {
                    trackingMovement
                },
            },
            message: Translation.of('Traçabilité', 'Mouvements', 'Voulez-vous réellement supprimer ce mouvement ?', false),
            title: Translation.of('Traçabilité', 'Mouvements', 'Supprimer le mouvement', false),
            validateButton: {
                color: 'danger',
                label: Translation.of('Général', null, 'Modale', 'Supprimer', false),
            },
            cancelButton: {
                label: Translation.of('Général', null, 'Modale', 'Annuler', false),
            },
            table: tableMvt,
        });
    });

    const $modalNewMvtTraca = $("#modalNewMvtTraca");
    Form
        .create($modalNewMvtTraca)
        .onOpen(function () {
            fillDatePickers($modalNewMvtTraca.find('[name="datetime"]') , 'YYYY-MM-DD', true);
            Camera
                .init(
                    $modalNewMvtTraca.find(`.take-picture-modal-button`),
                    $modalNewMvtTraca.find(`[name="files[]"]`)
                );
        })
        .onSubmit(function (data, form) {
            const pack = $modalNewMvtTraca.find(`[name="pack"]`).val();
            const type = $modalNewMvtTraca.find(`[name="type"] option:selected`).text().trim();

            if (type !== `prise` || !pack) {
                submitNewTrackingMovementForm(data, form);
            } else {
                AJAX.route(GET, `article_is_in_lu`, {barcode: pack})
                    .json()
                    .then(result => {
                        if (result.in_logistic_unit) {
                            Modal.confirm({
                                title: `Article dans unité logistique`,
                                message: `L'article ${pack} sera enlevé de l'unité logistique ${result.logistic_unit}`,
                                validateButton: {
                                    color: 'success',
                                    label: 'Continuer',
                                    click: () => {
                                        submitNewTrackingMovementForm(data, form)
                                    },
                                },
                            });
                        } else {
                            submitNewTrackingMovementForm(data, form)
                        }
                    })
            }
        })
}

function submitNewTrackingMovementForm(data, form) {
    form.loading(
        () => AJAX
            .route(POST, 'mvt_traca_new', {})
            .json(data)
            .then(({success, group, trackingMovementsCounter, ...re}) => {
                const $modal = form.element;
                if (success) {
                    [tableMvt].forEach((table) => {
                        if (table instanceof Function) {
                            table().ajax.reload();
                        } else {
                            table.ajax.reload();
                        }
                    })
                    if (group) {
                        displayConfirmationModal($modal, group);
                    } else {
                        displayOnSuccessCreation(success, trackingMovementsCounter);
                        fillDatePickers('.free-field-date');
                        fillDatePickers('.free-field-datetime', 'YYYY-MM-DD', true);

                        form.clear();
                        if (!Boolean(Number($('[name="CLEAR_AND_KEEP_MODAL_AFTER_NEW_MVT"]').val()))) {
                            $modal.modal('hide');
                        }
                    }
                }
            })
    );
}

function initNewModal($modal) {
    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    Select2Old.user($operatorSelect, 'Opérateur');
}

function resetNewModal($modal) {
    // date
    const date = moment().format();
    $modal.find('.datetime').val(date.slice(0, 16));

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
    let paramsToGetAppropriateHtml = $input.val();
    const $modal = $input.closest('.modal');

    if (paramsToGetAppropriateHtml) {
        $modal.find(`[type="submit"]`).prop(`disabled`, false);
        AJAX.route(AJAX.POST, "mouvement_traca_get_appropriate_html")
            .json(paramsToGetAppropriateHtml)
            .then((response) => {
                if (response) {
                    $modal.find('.more-body-new-mvt-traca').html(response.modalBody);
                    $modal.find('.new-mvt-common-body').removeClass('d-none');
                    $modal.find('.more-body-new-mvt-traca').removeClass('d-none');
                    Select2Old.location($modal.find('.ajax-autocomplete-location'));

                    const $emptyRound = $modal.find('input[name="empty-round"]');
                    if ($input.find(':selected').text().trim() === $emptyRound.val()) {
                        const $packInput = $modal.find('select[name="pack"]');
                        $modal.find('input[name="quantity"]').closest('div.form-group').addClass('d-none');
                        $packInput.val('passageavide');
                        $packInput.prop('disabled', true);
                    }

                    const $moreMassMvtContainer = $modal.find('.form-mass-mvt-container');
                    if ($moreMassMvtContainer.length > 0) {
                        const $emplacementPrise = $moreMassMvtContainer.find('.ajax-autocomplete-location[name="emplacement-prise"]');
                        const $emplacementDepose = $moreMassMvtContainer.find('.ajax-autocomplete-location[name="emplacement-depose"]');
                        const $pack = $moreMassMvtContainer.find('select[name="pack"]');

                        Select2Old.location($emplacementPrise, {autoSelect: true, $nextField: $pack});
                        Select2Old.location($emplacementDepose, {autoSelect: true});

                        setTimeout(() => $emplacementPrise.select2('open'), 200);
                    }
                }
            });
    }
}

/**
 * Used in mouvement_association/index.html.twig
 */
function clearURL() {
    window.history.pushState({}, document.title, `${window.location.pathname}`);
}

function displayConfirmationModal($trackingMovementModal, group) {
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
                    $trackingMovementModal
                        .find('input[name="forced"]')
                        .val(0);
                    $modal.modal('hide');
                }
            },
            {
                class: 'btn btn-success m-0',
                text: 'Oui',
                action: ($modal) => {
                    $trackingMovementModal
                        .find('input[name="forced"]')
                        .val(1);

                    $modal.modal('hide');

                    $trackingMovementModal
                        .find(`button[type="submit"]`)
                        .trigger(`click`);

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

function toggleDateInput($checkbox) {
    const isChecked = $checkbox.is(`:checked`);

    $checkbox
        .siblings(`label`)
        .closest(`.form-group`)
        .find(`[name="datetime"]`)
        .toggleClass(`d-none`, isChecked)
        .toggleClass(`needed`, !isChecked);
}

