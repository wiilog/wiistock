import AJAX, {POST} from "@app/ajax";
import Form from "@app/form";
import Routing from '@app/fos-routing';
import Camera from "@app/camera";
import Flash from "@app/flash";
import {wrapLoadingOnActionButton} from "@app/loading";
import {createFormNewDispatch, initNewDispatchEditor, onDispatchTypeChange} from "@app/pages/dispatch/common";


let tableDispatches = null;

global.displayCommentNeededAttributes = displayCommentNeededAttributes;
global.onDispatchTypeChange = onDispatchTypeChange

$(function() {
    initTableDispatch(false).then((returnedDispatchTable) => {
        tableDispatches = returnedDispatchTable;
        initPage();
    });

    const filtersContainer = $('.filters-container');
    const fromDashboard = $('.filters-container [name="fromDashboard"]').val() === '1';

    Select2Old.init(filtersContainer.find('.filter-select2[name="carriers"]'), Translation.of('Demande', 'Acheminements', 'Général', 'Transporteurs', false));
    Select2Old.init(filtersContainer.find('.filter-select2[name="emergencyMultiple"]'), Translation.of('Demande', 'Général','Urgences', false));
    Select2Old.dispatch(filtersContainer.find('.filter-select2[name="dispatchNumber"]'), Translation.of('Demande', 'Acheminements', 'Général', 'N° demande', false));
    Select2Old.init(filtersContainer.find('.filter-select2[name="multipleTypes"]'), Translation.of('Demande', 'Acheminements', 'Général', 'Types', false));
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
        if (!fromDashboard) {
            displayFiltersSup(data, true);
        }
    }, 'json');

    const $modalNewDispatch = $('#modalNewDispatch');
    $modalNewDispatch.on('show.bs.modal', function () {
        initNewDispatchEditor('#modalNewDispatch');
        Camera.init(
            $modalNewDispatch.find(`.take-picture-modal-button`),
            $modalNewDispatch.find(`[name="files[]"]`)
        );
    });

    const $dispatchsTable = $(`#tableDispatches`);
    const $dispatchModeContainer = $(`.dispatch-button-container`);
    const $groupedSignatureModeContainer = $(`.grouped-signature-button-container`);
    const $filtersInputs = $(`.filters-container`).find(`select, input, button, .checkbox-filter`);
    $(`.grouped-signature-mode-button`).on(`click`, function() {
        const $button = $(this);
        const $filtersContainer = $(".filters-container");
        const $statutFilterOptionSelected = $filtersContainer.find(`select[name=statut] option:selected`);
        const $typeFilterOptionSelected = $filtersContainer.find(`select[name=multipleTypes] option:selected`);
        const pickLocationFilterValue = $filtersContainer.find(`select[name=pickLocation]`).val();
        const dropLocationFilterValue = $filtersContainer.find(`select[name=dropLocation]`).val();

        // check if page filters valids
        if ($statutFilterOptionSelected.data('allowed-state')
            && $statutFilterOptionSelected.length === 1
            && $typeFilterOptionSelected.length === 1
            && (pickLocationFilterValue || dropLocationFilterValue)){
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
                "Veuillez saisir un statut, un type ainsi qu'un Emplacement de prise ou de dépose dans les filtres en haut de page."
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
                        displayCommentNeededAttributes($modalGroupedSignature.find('select[name=status]'));
                        Form.create($modalGroupedSignature)
                            .clearSubmitListeners()
                            .onSubmit((data, form) => {
                                form.loading(() => (
                                    AJAX.route(AJAX.POST, 'finish_grouped_signature', {
                                        dispatchesToSign,
                                        from: pickLocationFilterValue,
                                        to: dropLocationFilterValue
                                    })
                                        .json(data)
                                        .then(({success, msg}) => {
                                            if(success){
                                                window.location.reload();
                                            }
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

    initFilterStatusMutiple();
});

function initTableDispatch(groupedSignatureMode = false) {
    const $filtersContainer = $(".filters-container");
    const fromDashboard = $filtersContainer.find('[name="fromDashboard"]').val();
    const hasRightGroupedSignature = $filtersContainer.find('[name="hasRightGroupedSignature"]').val();
    const $statutFilter = $filtersContainer.find(`select[name=statut]`);
    const $typeFilter = $filtersContainer.find(`select[name=multipleTypes]`);
    const $pickLocationFilter = $filtersContainer.find(`select[name=locationPickWithGroups]`);
    const $dropLocationFilter = $filtersContainer.find(`select[name=locationDropWithGroups]`);
    const $emergencyFilter = $filtersContainer.find(`select[name=emergencyMultiple]`);
    let statuts;

    if(Boolean(hasRightGroupedSignature)){
        statuts = $statutFilter.val();
    } else {
        statuts = $filtersContainer.find(`.statuses-filter [name*=statuses-filter]:checked`)
            .map((index, line) => $(line).data('id'))
            .toArray();

        updateSelectedStatusesCount(statuts.length);
    }

    let pathDispatch = Routing.generate('dispatch_api', {
        groupedSignatureMode,
        fromDashboard,
        filterStatus: statuts,
        preFilledTypes: $typeFilter.val(),
        pickLocationFilter: $pickLocationFilter.val() || [],
        dropLocationFilter: $dropLocationFilter.val() || [],
        emergencyFilter: $emergencyFilter.val() || [],
    }, true);

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
            columns,
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

        return initDataTable('tableDispatches', tableDispatchConfig);
    }
}

function initPage() {
    let $modalNewDispatch = $("#modalNewDispatch");
    const keepModalOpenAndClearAfterSubmit = $modalNewDispatch.find('[name=keepModalOpenAndClearAfterSubmit]').val();
    createFormNewDispatch($modalNewDispatch)
        .submitTo(
            AJAX.POST,
            'dispatch_new',
            {
                tables: [tableDispatches],
                success: ({redirect}) => {
                    if (!keepModalOpenAndClearAfterSubmit) {
                        window.location.href = redirect;
                    }
                },
                keepModal: !!keepModalOpenAndClearAfterSubmit,
                clearFields: !!keepModalOpenAndClearAfterSubmit,
            }
        )
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
    updateRequiredMark($labelContainer, required);
}
