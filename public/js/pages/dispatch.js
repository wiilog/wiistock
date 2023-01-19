let tableDispatches = null;

$(function() {
    initTableDispatch(false).then((returnedDispatchTable) => {
        tableDispatches = returnedDispatchTable;
        initPage();
    });

    const filtersContainer = $('.filters-container');

    Select2Old.init(filtersContainer.find('.filter-select2[name="carriers"]'), Translation.of('Demande', 'Acheminements', 'Général', 'Transporteurs', false));
    Select2Old.init(filtersContainer.find('.filter-select2[name="emergencyMultiple"]'), Translation.of('Demande', 'Général','Urgences', false));
    Select2Old.dispatch(filtersContainer.find('.filter-select2[name="dispatchNumber"]'), Translation.of('Demande', 'Acheminements', 'Général', 'N° demande', false));
    Select2Old.init(filtersContainer.find('.filter-select2[name="multipleTypes"]'), Translation.of('Demande', 'Acheminements', 'Général', 'Types', false));
    Select2Old.initFree(filtersContainer.find('.filter-select2[name="commandList"]'), Translation.of('Demande', 'Acheminements', 'Champs fixes', 'N° commande', false));
    Select2Old.user(filtersContainer.find('.ajax-autocomplete-user[name=receivers]'), Translation.of('Demande', 'Général', 'Destinataire(s)', false));
    Select2Old.user(filtersContainer.find('.ajax-autocomplete-user[name=requesters]'), Translation.of('Demande', 'Général', 'Demandeurs', false));
    Select2Old.location(filtersContainer.find('[name=pickLocation]'), {}, Translation.of('Demande', 'Acheminements', 'Champs fixes','Emplacement de prise', false));
    Select2Old.location(filtersContainer.find('[name=dropLocation]'), {}, Translation.of('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de dépose', false));
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_DISPATCHES);
    $.post(path, params, function(data) {
        displayFiltersSup(data, true);
    }, 'json');

    const $modalNewDispatch = $('#modalNewDispatch');
    $modalNewDispatch.on('show.bs.modal', function () {
        initNewDispatchEditor('#modalNewDispatch');
    });

    const $dispatchsTable = $(`#tableDispatches`);
    const $dispatchModeContainer = $(`.dispatch-button-container`);
    const $groupedSignatureModeContainer = $(`.grouped-signature-button-container`);
    const $filtersInputs = $(`.filters-container`).find(`select, input, button, .checkbox-filter`);
    $(`.grouped-signature-mode-button`).on(`click`, function() {
        const $button = $(this);
        const $filtersContainer = $(".filters-container");
        const $statutFilterOptionSelected = $filtersContainer.find(`select[name=statut] option:selected`);
        const pickLocationFilterValue = $filtersContainer.find(`select[name=pickLocation]`).val();
        const dropLocationFilterValue = $filtersContainer.find(`select[name=dropLocation]`).val();

        // check if page filters valids
        if ($statutFilterOptionSelected.data('allowed-state')
            && $statutFilterOptionSelected.length === 1
            && (pickLocationFilterValue !== null || dropLocationFilterValue !== null)){
            wrapLoadingOnActionButton($button, () => {
                return saveFilters('acheminement', '#tableDispatches', null, 1).then(() => {
                    tableDispatches.clear().destroy();
                    return initTableDispatch(true).then((returnedDispatchsTable) => {
                        tableDispatches = returnedDispatchsTable;
                        $(`.dataTables_filter`).parent().remove();
                        $dispatchModeContainer.addClass(`d-none`);
                        $groupedSignatureModeContainer.removeClass(`d-none`);
                        $filtersInputs.prop(`disabled`, true).addClass(`disabled`);
                    });
                });
            })
        } else {
            Flash.add(
                Flash.ERROR,
                "Veuillez saisir un statut en état Brouillon ou A Traité ainsi qu'un Emplacement de prise ou de dépose dans les filtres en haut de page."
            );
        }
    });

    $groupedSignatureModeContainer.find(`.cancel`).on(`click`, function() {
        const $button = $(this);
        $groupedSignatureModeContainer.find(`.validate`).prop(`disabled`, true);
        wrapLoadingOnActionButton($button, () => {
            tableDispatches.clear().destroy();
            return initTableDispatch(false).then((returnedArrivalsTable) => {
                tableDispatches = returnedArrivalsTable;
                $dispatchModeContainer.removeClass(`d-none`);
                $groupedSignatureModeContainer.addClass(`d-none`);
                $filtersInputs.prop(`disabled`, false).removeClass(`disabled`);
            });
        })
    });

    $groupedSignatureModeContainer.find(`.validate`).on(`click`, function() {
        const $button = $(this);
        const $checkedCheckboxes = $dispatchsTable.find(`input[type=checkbox]:checked`).not(`.check-all`);
        const dispatchesToSign = $checkedCheckboxes.toArray().map((element) => $(element).val());
        if(dispatchesToSign.length > 0) {
            wrapLoadingOnActionButton($button, () => (
                AJAX.route(AJAX.GET, `grouped_signature_modal_content`, {
                    statusId: $(`.filters-container select[name=statut] option:selected`).first().val(),
                    dispatchesToSign,
                })
                    .json()
                    .then(({content}) => {
                        $(`body`).append(content);

                        let $modalGroupedSignature = $("#modalGroupedSignature");

                        const pickLocationFilterValue = $(`.filters-container select[name=pickLocation]`).val();
                        const dropLocationFilterValue = $(`.filters-container select[name=dropLocation]`).val();
                        const location = pickLocationFilterValue !== null
                            ? pickLocationFilterValue
                            : (dropLocationFilterValue !== null
                                ? dropLocationFilterValue
                                : '');
                        displayCommentNeededAttributes($modalGroupedSignature.find('select[name=status]'));
                        Form.create($modalGroupedSignature)
                            .clearSubmitListeners()
                            .onSubmit((data, form) => {
                                form.loading(() => (
                                    AJAX.route(AJAX.POST, 'finish_grouped_signature', {dispatchesToSign, location})
                                        .json(data)
                                        .then(() => {
                                            window.location.reload();
                                        })
                                ), false)
                            })

                        $modalGroupedSignature.modal(`show`);
                    })
            ));

        }
    });

    $(document).arrive(`.check-all`, function () {
        $(this).on(`click`, function() {
            $dispatchsTable.find(`.dispatch-checkbox`).not(`:disabled`).prop(`checked`, $(this).is(`:checked`));
            toggleValidateGroupedSignatureButton($dispatchsTable, $groupedSignatureModeContainer);
        });
    });

    $(document).on(`change`, `.dispatch-checkbox:not(:disabled)`, function() {
        toggleValidateGroupedSignatureButton($dispatchsTable, $groupedSignatureModeContainer);
    });
});

function initTableDispatch(groupedSignatureMode = false) {
    let pathDispatch = Routing.generate('dispatch_api', {groupedSignatureMode}, true);
    let initialVisible = $(`#tableDispatches`).data(`initial-visible`);
    if(groupedSignatureMode || !initialVisible) {
        return $.post(Routing.generate('dispatch_api_columns', {groupedSignatureMode}))
            .then(columns => proceed(columns));
    } else {
        return new Promise((resolve) => {
            resolve(proceed(initialVisible));
        });
    }

    function proceed(columns) {
        let tableDispatchConfig = {
            serverSide: !groupedSignatureMode,
            processing: true,
            pageLength: 10,
            order: [[1, "desc"]],
            ajax: {
                "url": pathDispatch,
                "type": "POST",
            },
            rowConfig: {
                needsRowClickAction: true,
                needsColor: true,
                color: 'danger',
                dataToCheck: 'emergency'
            },
            drawConfig: {
                needsSearchOverride: true,
            },
            columns,
            hideColumnConfig: {
                columns,
                tableFilter: 'tableDispatches'
            },
            page: 'dispatch',
            disabledRealtimeReorder: groupedSignatureMode,
            createdRow: (row) => {
                if (groupedSignatureMode) {
                    $(row).addClass('pointer user-select-none');
                }
            }
        };

        if (groupedSignatureMode) {
            extendsDateSort('customDate');
        }

        const dispatchsTable = initDataTable('tableDispatches', tableDispatchConfig);
        dispatchsTable.on('responsive-resize', function () {
            resizeTable(dispatchsTable);
        });


        return dispatchsTable;
    }
}

function initPage() {
    let $modalNewDispatch = $("#modalNewDispatch");
    let $submitNewDispatch = $("#submitNewDispatch");
    let urlDispatchNew = Routing.generate('dispatch_new', true);
    InitModal($modalNewDispatch, $submitNewDispatch, urlDispatchNew, {tables: [tableDispatches]});
}

function toggleValidateGroupedSignatureButton($dispatchsTable, $groupedSignatureModeContainer) {
    const $allDispatchCheckboxes = $(`.dispatch-checkbox`).not(`:disabled`);
    const atLeastOneChecked = $allDispatchCheckboxes.toArray().some((element) => $(element).is(`:checked`));

    $groupedSignatureModeContainer.find(`.validate`).prop(`disabled`, !atLeastOneChecked);
    $(`.check-all`).prop(`checked`, ($allDispatchCheckboxes.filter(`:checked`).length) === $allDispatchCheckboxes.length);
}

function displayCommentNeededAttributes(statusSelect){
    const $modalGroupedSignature = $("#modalGroupedSignature");
    const $comment = $modalGroupedSignature.find('input[name=comment]');
    const required = Boolean(statusSelect.find('option:selected').data('needed-comment'));
    $comment.toggleClass('needed', required);

    const $labelContainer = $comment.closest('.form-group').find('.field-label');
    $labelContainer.find('.required-mark').remove();
    if (required) {
        $labelContainer.append($('<span class="required-mark">*</span>'))
    }
}
