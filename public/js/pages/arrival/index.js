$('.select2').select2();

let onFlyFormOpened = {};
let clicked = false;
let pageLength;
let arrivalsTable;

$(function () {
    const $filtersContainer = $('.filters-container');
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initDateTimePicker('#dateMin, #dateMax, .date-cl', DATE_FORMATS_TO_DISPLAY[format]);
    Select2Old.location($('#emplacement'), {}, Translation.of('Traçabilité', 'Mouvements', 'Emplacement de dépose', false));
    Select2Old.init($filtersContainer.find('[name="carriers"]'), Translation.of('Traçabilité', 'Flux - Arrivages', 'Divers', 'Transporteurs', false));
    initOnTheFlyCopies($('.copyOnTheFly'));

    initTableArrival(false).then((returnedArrivalsTable) => {
        arrivalsTable = returnedArrivalsTable;
    });

    const filters = JSON.parse($(`#arrivalFilters`).val())
    displayFiltersSup(filters, true);

    pageLength = Number($('#pageLengthForArrivage').val());
    Select2Old.provider($('.ajax-autocomplete-fournisseur'), Translation.of('Traçabilité', 'Flux - Arrivages', 'Divers', 'Fournisseurs', false));

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

    $dispatchModeContainer.find(`.validate`).on(`click`, function () {
        const $checkedCheckboxes = $arrivalsTable.find(`input[type=checkbox]:checked`).not(`.check-all`);
        const arrivalsToDispatch = $checkedCheckboxes.toArray().map((element) => $(element).val());
        if (arrivalsToDispatch.length > 0) {
            $(this).pushLoader(`white`);
            $.post(Routing.generate(`create_from_arrival_template`, {arrivals: arrivalsToDispatch}, true))
                .then(({content}) => {
                    $(this).popLoader();
                    $(`body`).append(content);

                    let $modalNewDispatch = $("#modalNewDispatch");
                    $modalNewDispatch.modal(`show`);

                    let $submitNewDispatch = $("#submitNewDispatch");
                    let urlDispatchNew = Routing.generate('dispatch_new', true);
                    InitModal($modalNewDispatch, $submitNewDispatch, urlDispatchNew);

                    initNewDispatchEditor('#modalNewDispatch');
                });
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
                needsSearchOverride: true,
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
            hideColumnConfig: {
                columns,
                tableFilter: 'arrivalsTable'
            },
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

        const arrivalsTable = initDataTable('arrivalsTable', tableArrivageConfig);
        arrivalsTable.on('responsive-resize', function () {
            resizeTable(arrivalsTable);
        });

        return arrivalsTable;
    }
}

function listColis(elem) {
    let arrivageId = elem.data('id');
    let path = Routing.generate('arrivage_list_colis_api', true);
    let modal = $('#modalListColis');
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
        onTypeChange($modal.find('[name="type"]'));
        initDateTimePicker('.date-cl');

        onFlyFormOpened = {};
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
        const $noTrackingSelect = $modal.find("select[name='noTracking']");
        const $noTruckArrivalSelect = $modal.find("input[name='noTruckArrival']");

        $carrierSelect.on(`change`, function () {
            $noTrackingSelect
                .prop(`disabled`, !$(this).val())
                .attr('data-other-params-carrier-id', $(this).val())
                .attr('data-other-params-truck-arrival-id', null);
            $noTrackingSelect.find(`option`).remove();
            $noTrackingSelect.trigger('change');
            $noTruckArrivalSelect.val(``);
        }).trigger('change');

        const trackingNumberSuccess = function (data) {
            const $driverSelect = $modal.find("select[name='chauffeur']");
            const $driverAddButton = $modal.find(`button.add-driver`);
            const $flyFormDirver = $modal.find(`.fly-form.driver`);

            $noTrackingSelect.attr('data-other-params-truck-arrival-id', data.truck_arrival_id || null);
            $noTruckArrivalSelect.val(data.truck_arrival_number);

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
        }
        $noTrackingSelect.on('select2:unselect', function(element) {
            $noTrackingSelect.find(`option[value=${element.params.data.id}]`).remove();
        })
        $noTrackingSelect.on(`select2:select`, function (element) {
            const data = element.params.data || {};
            if (data.arrivals_id) {
                displayAlertModal(
                    undefined,
                    $('<div/>', {
                        class: 'text-center',
                        html: `<span class="bold">N° de tracking transporteur : ${data.text}</span><br><br>Ce numéro de tracking transporteur à déjà été associé une fois à un arrivage. ` +
                            `Voulez vous l\'associer à nouveau ?`
                    }),
                    [
                        {
                            class: 'btn btn-outline-secondary m-0',
                            text: 'Annuler',
                            action: ($alert) => {
                                const selectedOptions = [];
                                $noTrackingSelect.find('option').each(function() {
                                    if ($(this).text() !== data.text) {
                                        selectedOptions.push({
                                            value: $(this).val(),
                                            text: $(this).text(),
                                        })
                                    }
                                });
                                $noTrackingSelect.find('option').remove();
                                $noTrackingSelect.val(null).trigger('change');
                                selectedOptions.forEach((option) => {
                                    $noTrackingSelect.append(new Option(option.text, option.value, true, true));
                                })
                                $noTrackingSelect.trigger('change');
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
        });

        const $submit = $modal.find(`[type=submit]`);
        $submit
            .off('click.new-arrival')
            .on('click.new-arrival', function () {
                if ($submit.hasClass(LOADING_CLASS)) {
                    Flash.add(`info`, Translation.of('Général', '', 'Modale', 'L\'opération est en cours de traitement'));
                    return;
                }

                SubmitAction($modal, $submit, Routing.generate('arrivage_new', true), {
                    keepForm: true,
                    keepModal: true,
                    keepLoading: true,
                    waitForUserAction: () => {
                        return checkPossibleCustoms($modal);
                    },
                    success: (res) => {
                        res = res || {};
                        let newForm = JSON.parse(res.new_form);
                        $(`#arrivalForm`).val(res.new_form);
                        createArrival(newForm);
                        if (res.success === false) {
                            $submit.popLoader();
                        }

                        arrivalCallback(
                            true,
                            {
                                ...(res || {}),
                                success: () => {
                                }
                            },
                            arrivalsTable
                        );

                        $.get(Routing.generate(`arrivage_new_api`, true), function (data) {
                            $(`#arrivalForm`).val(JSON.stringify(data));
                        });
                    },
                }).catch(() => {
                });
            })
    }, 1);

    return $modal;
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
