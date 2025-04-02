import {initDispatchCreateForm, onDispatchTypeChange} from "@app/pages/dispatch/common";
import {arrivalCallback, printArrival, checkPossibleCustoms} from "@app/pages/arrival/common";

global.listPacks = listPacks;
global.printArrival = printArrival;
global.openArrivalCreationModal = openArrivalCreationModal;
global.onDispatchTypeChange = onDispatchTypeChange

$('.select2').select2();

let clicked = false;
let pageLength;
let arrivalsTable;
let hasDataToRefresh;

$(function () {

    hasDataToRefresh = false;
    const openNewModal = Boolean($('#openNewModal').val());
    if(openNewModal){
        openArrivalCreationModal();
    }

    document.addEventListener('keydown', function (e) {
        const $target = $(e.target);
        const isTrackingNumbersInput = $target.parents('.noTrackingSection').length > 0;
        if (e.which === 13 && isTrackingNumbersInput) {
            e.stopPropagation();
            e.preventDefault();
        }
    }, true);

    const $filtersContainer = $('.filters-container');
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initArrivalFormEvent();
    initDateTimePicker('#dateMin, #dateMax, .date-cl', DATE_FORMATS_TO_DISPLAY[format]);
    Select2Old.location($('#emplacement'), {}, Translation.of('Traçabilité', 'Mouvements', 'Emplacement de dépose', false));
    Select2Old.init($filtersContainer.find('[name="carriers"]'), Translation.of('Traçabilité', 'Arrivages UL', 'Divers', 'Transporteurs', false));
    initOnTheFlyCopies($('.copyOnTheFly'));

    initTableArrival(false).then((returnedArrivalsTable) => {
        arrivalsTable = returnedArrivalsTable;
    });

    const filters = JSON.parse($(`#arrivalFilters`).val())
    displayFiltersSup(filters, true);

    pageLength = Number($('#pageLengthForArrivage').val());
    Select2Old.provider($('.ajax-autocomplete-fournisseur'), Translation.of('Traçabilité', 'Arrivages UL', 'Divers', 'Fournisseurs', false));

    const $arrivalsTable = $(`#arrivalsTable`);
    const $dispatchModeContainer = $(`.dispatch-mode-container`);
    const $arrivalModeContainer = $(`.arrival-mode-container`);
    const $filtersInputs = $(`.filters-container`).find(`select, input, button, .checkbox-filter`);
    $(`.dispatch-mode-button`).on(`click`, function () {
        $(this).pushLoader(`black`);
        arrivalsTable.clear().destroy();
        initTableArrival(true).then((returnedArrivalsTable) => {
            arrivalsTable = returnedArrivalsTable;
            $(`.dataTables_filter`).parent().remove();
            $dispatchModeContainer.removeClass(`d-none`);
            $arrivalModeContainer.addClass(`d-none`);
            $filtersInputs.prop(`disabled`, true).addClass(`disabled`);
            $(this).popLoader();
        });
    });

    let $modalNewDispatch = $("#modalNewDispatch");

    $dispatchModeContainer.find(`.validate`).on(`click`, function () {
        const $checkedCheckboxes = $arrivalsTable.find(`input[type=checkbox]:checked`).not(`.check-all`);
        const arrivalsToDispatch = $checkedCheckboxes.toArray().map((element) => $(element).val());

        initDispatchCreateForm($modalNewDispatch, 'arrivals', arrivalsToDispatch);
        if (arrivalsToDispatch.length > 0) {
            $modalNewDispatch.modal(`show`);
        }
    });

    $dispatchModeContainer.find(`.cancel`).on(`click`, function () {
        $dispatchModeContainer.find(`.validate`).prop(`disabled`, true);
        $(this).pushLoader(`primary`);
        arrivalsTable.clear().destroy();
        initTableArrival(false).then((returnedArrivalsTable) => {
            arrivalsTable = returnedArrivalsTable;
            $arrivalModeContainer.removeClass(`d-none`);
            $dispatchModeContainer.addClass(`d-none`);
            $filtersInputs.prop(`disabled`, false).removeClass(`disabled`);
            $(this).popLoader();
        });
    });

    $(document).arrive(`.check-all`, function () {
        $(this).on(`click`, function () {
            $arrivalsTable.find(`.dispatch-checkbox`).not(`:disabled`).prop(`checked`, $(this).is(`:checked`));
            toggleValidateDispatchButton($arrivalsTable, $dispatchModeContainer);
        });
    });

    $(document).on(`change`, `.dispatch-checkbox:not(:disabled)`, function () {
        toggleValidateDispatchButton($arrivalsTable, $dispatchModeContainer);
    });
});

function initTableArrival(dispatchMode = false) {
    let pathArrivage = Routing.generate('arrivage_api', {dispatchMode}, true);
    let initialVisible = $(`#arrivalsTable`).data(`initial-visible`);
    if (dispatchMode || !initialVisible) {
        return $
            .post(Routing.generate('arrival_api_columns', {dispatchMode}))
            .then(columns => proceed(columns));
    } else {
        return new Promise((resolve) => {
            resolve(proceed(initialVisible));
        });
    }

    function proceed(columns) {
        let tableArrivageConfig = {
            serverSide: !dispatchMode,
            processing: true,
            pageLength: Number($('#pageLengthForArrivage').val()),
            order: [['creationDate', "desc"]],
            ajax: {
                "url": pathArrivage,
                "type": "POST",
                'data': {
                    'clicked': () => clicked,
                }
            },
            columns,
            drawConfig: {
                needsResize: true,
                hidePaging: dispatchMode,
            },
            rowConfig: {
                needsColor: true,
                color: 'danger',
                needsRowClickAction: true,
                dataToCheck: 'checkEmergency'
            },
            buttons: [
                {
                    extend: 'colvis',
                    columns: ':not(.noVis)',
                    className: 'd-none'
                },
            ],
            columnDefs: [{
                type: "customDate",
                targets: "creationDate"
            }],
            lengthMenu: [10, 25, 50, 100],
            page: 'arrival',
            disabledRealtimeReorder: dispatchMode,
            initCompleteCallback: () => {
                updateArrivalPageLength();
                $('.dispatch-mode-button').removeClass('d-none');
                $('button[name=new-arrival]').attr('disabled', false);
                if (dispatchMode) {
                    $(`.dispatch-mode-container`).find(`.cancel`).prop(`disabled`, false);
                }
            },
            createdRow: (row) => {
                if (dispatchMode) {
                    $(row).addClass('pointer user-select-none');
                }
            }
        };

        if (dispatchMode) {
            extendsDateSort('customDate');
        }

        return initDataTable('arrivalsTable', tableArrivageConfig);
    }
}

function listPacks(elem) {
    let arrivageId = elem.data('id');
    let path = Routing.generate('arrivage_list_packs_api');
    let modal = $('#modalListPacks');
    let params = {id: arrivageId};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.modal-body').html(data);
    }, 'json');
}

function openArrivalCreationModal() {
    const $modal = createArrival();
    $modal.modal({
        backdrop: 'static',
        keyboard: false,
    });

    $modal.modal(`show`);
}

function createArrival(form = null) {
    const data = form || JSON.parse($(`#arrivalForm`).val());
    const $existingModal = $(`#modalNewArrivage`);
    let $modal;
    if ($existingModal.exists()) {
        const style = $existingModal.attr(`style`);
        $existingModal.find('.modal-body').html($(data.html).find('.modal-body').html());

        $modal = $(`#modalNewArrivage`);
        $modal.attr(`style`, style);
        $modal.addClass(`show`);

        // remove errors from previous form
        $modal.find('.is-invalid').removeClass('is-invalid');
        $modal.find('.error-msg').text('');

        $modal.find('[type=submit]').popLoader();
    } else {
        $(`body`).append(data.html);

        $modal = $(`#modalNewArrivage`);
    }

    const $element = $modal.find("select[name='noTracking']");
    if ($element.is(`.select2-hidden-accessible`)) {
        $element.val(null).html(``);
        $element.select2(`data`, null);
        $element.select2(`destroy`);
    }

    setTimeout(() => {
        Camera.init(
            $modal.find(`.take-picture-modal-button`),
            $modal.find(`[name="files[]"]`)
        );

        onTypeChange($modal.find('[name="type"]'));
        initDateTimePicker('.date-cl');

        onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
        onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
        onFlyFormToggle('chauffeurDisplay', 'addChauffeur', true);

        Select2Old.provider($modal.find('.ajax-autocomplete-fournisseur'));
        Select2Old.init($modal.find('.ajax-autocomplete-transporteur'));
        Select2Old.init($modal.find('.ajax-autocomplete-chauffeur'));
        Select2Old.location($modal.find('.ajax-autocomplete-location'));
        Select2Old.init($modal.find('.ajax-autocomplete-user'), '', 1);
        Select2Old.initFree($modal.find('.select2-free'));

        const $userFormat = $('#userDateFormat');
        const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
        initDateTimePicker('.free-field-date', DATE_FORMATS_TO_DISPLAY[format]);
        initDateTimePicker('.free-field-datetime', DATE_FORMATS_TO_DISPLAY[format] + ' HH:mm');

        fillDatePickers('.free-field-date');
        fillDatePickers('.free-field-datetime', 'YYYY-MM-DD', true);

        const $carrierSelect = $modal.find("select[name='transporteur']");
        const $driverSelect = $modal.find("select[name='chauffeur']");
        const $noTrackingSelect = $modal.find("select[name='noTracking']");
        const $noTruckArrivalSelect = $modal.find("select[name='noTruckArrival']");

        // disable carrier select if no truck arrival is selected
        $noTruckArrivalSelect.on('change', function () {
            if ($noTruckArrivalSelect.val()) {
                $carrierSelect.attr('disabled', true);
            } else {
                $carrierSelect.attr('disabled', false);
            }
        });

        $carrierSelect.on(`change`, function () {
            $noTrackingSelect
                .prop(`disabled`, !$(this).val())
                .attr('data-other-params-carrier-id', $(this).val())
                .attr('data-other-params-truck-arrival-id', null);
            $noTrackingSelect.find(`option`).remove();
            $noTrackingSelect.trigger('change');
            $noTruckArrivalSelect.attr('data-other-params-carrier-id', $(this).val());
            $noTruckArrivalSelect.find(`option:selected`).prop(`selected`, false);
            $noTruckArrivalSelect.trigger('change');
        }).trigger('change');

        $noTruckArrivalSelect.off('select2:select').on(`select2:select`, function ($select) {
            const data = $select.params.data || {};
            const trackingNumberRequired = Boolean($modal.find(`[name=trackingNumberRequired]`).val());
            if (!trackingNumberRequired){
                if(data.carrier_id){
                    $carrierSelect.append(`<option value="${data.carrier_id}" selected>${data.carrier_label}</option>`);
                    $noTrackingSelect
                        .prop(`disabled`, !$(this).val())
                        .attr('data-other-params-carrier-id', data.carrier_id)
                        .attr('data-other-params-truck-arrival-id', $(this).val());

                    const newTrackingNumbers = $noTrackingSelect.select2('data')
                        ?.filter(({isNewElement}) => isNewElement)
                        .map(({id}) => id) || [];
                    $noTrackingSelect.find(`option`).each(function() {
                        if (!newTrackingNumbers.includes($(this).val())) {
                            $(this).remove();
                        }
                    });
                }

                if(data.driver_id){
                    $driverSelect.find(`option[value='${data.driver_id}']`).prop('selected', true).trigger('change');
                }
            }
        });

        $modal.find('.noTrackingSection').arrive('.select2-results__option--highlighted', function () {
            $(this).removeClass('select2-results__option--highlighted');
        });

        $noTrackingSelect
            .off('select2:unselect.new-arrival')
            .on('select2:unselect.new-arrival', function(event) {
                $noTrackingSelect.find(`option[value=${event.params.data.id}]`).remove();
            })
            .off('select2:select.new-arrival')
            .on(`select2:select.new-arrival`, function (event, option) {

                event.option = option
                onNoTrackingSelected($modal, event);
            })
            .off(`change.new-arrival`)
            .on(`change.new-arrival`, function () {
                const $selectedOptions = $(this).find(`option:selected`);
                if ($selectedOptions.length > 0) {
                    $noTruckArrivalSelect.addClass('needed');
                } else {
                    $noTruckArrivalSelect.removeClass('needed');
                }
            });

        const $submit = $modal.find(`[type=submit]`);
        $submit
            .off('click.new-arrival')
            .on('click.new-arrival', function () {
                if ($submit.hasClass(LOADING_CLASS)) {
                    Flash.add(`info`, Translation.of('Général', '', 'Modale', 'L\'opération est en cours de traitement'));
                    return;
                }

                const noTrackingValues = $modal.find("select[name='noTracking']").select2('data') || [];
                const newNoTrackingIds = noTrackingValues
                    .filter(({isNewElement}) => isNewElement)
                    .map(({id}) => id);
                $modal.find('[name="newTrackingNumbers"]').val(JSON.stringify(newNoTrackingIds));

                SubmitAction($modal, $submit, Routing.generate('arrivage_new', true), {
                    keepForm: true,
                    keepModal: true,
                    keepLoading: true,
                    waitForUserAction: () => {
                        return checkPossibleCustoms($modal);
                    },
                    validator: validatorNoTracking,
                    success: (res) => {
                        res = res || {};
                        let newForm = res.new_form;
                        if (newForm) {
                            $(`#arrivalForm`).val(JSON.stringify(newForm));
                            createArrival(newForm);
                        }

                        arrivalCallback(
                            true,
                            {
                                ...(res || {}),
                                success: () => {
                                }
                            },
                        );
                        hasDataToRefresh = true;
                    },
                }).catch((error) => {
                    console.log(error);
                });
            })
    }, 1);


    $modal.off('hide.bs.modal.refresh').on('hide.bs.modal.refresh', function () {
        if (hasDataToRefresh) {
            arrivalsTable.ajax.reload(() => {
                hasDataToRefresh = false;
            });
        }
    });
    return $modal;
}

function validatorNoTracking($modal) {
    const $noTracking = $modal.find('select[name=noTracking]');
    if ($noTracking.length !== 0) {
        const isValid = $noTracking.attr('required') === undefined || $noTracking.val()?.length > 0 && $noTracking.attr('required');
        if(!isValid) {
            return {
                success: false,
                errorMessages: ["Veuillez renseigner le champs 'N° de tracking transporteur'"],
                $isInvalidElements: [$noTracking],
            };
        }
    }
}

function updateArrivalPageLength() {
    pageLength = Number($('#pageLengthForArrivage').val());

    $('select[name="arrivalsTable_length"]').on('change', function () {
        let newValue = Number($(this).val());
        if (newValue && newValue !== pageLength) {
            $.post(Routing.generate('update_user_page_length_for_arrivage'), JSON.stringify(newValue));
            pageLength = newValue;
        }
    });
}

function toggleValidateDispatchButton($arrivalsTable, $dispatchModeContainer) {
    const $allDispatchCheckboxes = $(`.dispatch-checkbox`).not(`:disabled`);
    const atLeastOneChecked = $allDispatchCheckboxes.toArray().some((element) => $(element).is(`:checked`));

    $dispatchModeContainer.find(`.validate`).prop(`disabled`, !atLeastOneChecked);
    $(`.check-all`).prop(`checked`, ($allDispatchCheckboxes.filter(`:checked`).length) === $allDispatchCheckboxes.length);
}

function onNoTrackingSelected($modal, event) {
    const $noTrackingSelect = $modal.find("select[name='noTracking']");
    const $noTruckArrivalSelect = $modal.find("select[name='noTruckArrival']");

    const trackingNumberSuccess = function (data) {
        const $driverSelect = $modal.find("select[name='chauffeur']");
        const $driverAddButton = $modal.find(`button.add-driver`);
        const $flyFormDirver = $modal.find(`.fly-form.driver`);

        $noTrackingSelect.attr('data-other-params-truck-arrival-id', data.truck_arrival_id || null);
        if(data.truck_arrival_id){
            $noTruckArrivalSelect.append(new Option(data.truck_arrival_number, data.truck_arrival_id, true, true))
        } else {
            $noTruckArrivalSelect.attr(`disabled`, false);
        }

        if (data.driver_id !== undefined && data.driver_id != null) {
            $driverSelect.find(`option`).remove();
            $driverSelect
                .append(`<option value="${data.driver_id}" selected>${data.driver_first_name} ${data.driver_last_name}</option>`)
                .attr('disabled', true);
            $driverAddButton.attr('disabled', true);
            $flyFormDirver.css('height', '0px').addClass('invisible')
        } else {
            $driverSelect.attr('disabled', false);
            $driverAddButton.attr('disabled', false);
        }
    };

    const data = event.option || event.params.data || {};

    if (data.arrivals_id) {
        displayAlertModal(
            undefined,
            $('<div/>', {
                class: 'text-center',
                html: `
                    <span class="bold">N° de tracking transporteur : ${data.text}</span><br><br>
                    Ce numéro de tracking transporteur à déjà été associé une fois à un arrivage. Voulez vous l\'associer à nouveau ?
                `,
            }),
            [
                {
                    class: 'btn btn-outline-secondary m-0',
                    text: 'Annuler',
                    action: ($alert) => {
                        const selectedOptions = $noTrackingSelect.find('option').toArray()
                            .filter((element) => $(element).text() !== data.text)
                            .map((element) => ({
                                value: $(element).val(),
                                text: $(element).text(),
                            }))
                        $noTrackingSelect.find('option').remove();
                        $noTrackingSelect.val(null).trigger('change');
                        $noTrackingSelect
                            .append(
                                ...(selectedOptions.map(({value, text}) => new Option(text, value, true, true)))
                            )
                            .trigger('change');
                        $alert.modal('hide');
                    }
                },
                {
                    class: 'btn btn-success m-0 btn-action-on-hide',
                    text: 'Confirmer',
                    action: ($alert) => {
                        trackingNumberSuccess(data);
                        $alert.modal('hide');
                    }
                },
            ],
            'warning',
            false
        );
    } else {
        trackingNumberSuccess(data);
    }
}

function initArrivalFormEvent() {
    $(document)
        .on(`change`, `#modalNewArrivage [name=receivers]`, function () {
            const $recipient = $(this);
            const $modal = $recipient.closest('.modal');
            const defaultLocationIfRecipient = {
                id: $recipient.data('default-location-if-recipient-id'),
                label: $recipient.data('default-location-if-recipient-label'),
            };
            if($recipient.val() && $recipient.val().length > 0 && defaultLocationIfRecipient.id && defaultLocationIfRecipient.label) {
                $modal.find('[name=dropLocation]')
                    .append(new Option(defaultLocationIfRecipient.label, defaultLocationIfRecipient.id, false, true))
            }
        })
        .on(`change`, '#modalNewArrivage [name=customs]', function () {
            const $customs = $(this);
            const $modal = $customs.closest('.modal');
            const defaultLocationIfCustoms = {
                id: $customs.data('default-location-if-customs-id'),
                label: $customs.data('default-location-if-customs-label'),
            };
            if ($customs.is(':checked') && defaultLocationIfCustoms.id && defaultLocationIfCustoms.label) {
                $modal.find('[name=dropLocation]')
                    .append(new Option(defaultLocationIfCustoms.label, defaultLocationIfCustoms.id, false, true))
            }
        });
}
