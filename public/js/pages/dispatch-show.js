let packsTable;

$(function() {
    const dispatchId = $('#dispatchId').val();
    const isEdit = $(`#isEdit`).val();

    loadDispatchReferenceArticle();
    getStatusHistory(dispatchId);
    packsTable = initializePacksTable(dispatchId, isEdit);

    const $modalEditDispatch = $('#modalEditDispatch');
    const $submitEditDispatch = $('#submitEditDispatch');
    const urlDispatchEdit = Routing.generate('dispatch_edit', true);
    InitModal($modalEditDispatch, $submitEditDispatch, urlDispatchEdit, {
        success: () => window.location.reload()
    });

    const $modalValidateDispatch = $('#modalValidateDispatch');
    const $submitValidatedDispatch = $modalValidateDispatch.find('.submit-button');
    const urlValidateDispatch = Routing.generate('dispatch_validate_request', {id: dispatchId}, true);
    InitModal($modalValidateDispatch, $submitValidatedDispatch, urlValidateDispatch);

    const $modalTreatDispatch = $('#modalTreatDispatch');
    const $submitTreatedDispatch = $modalTreatDispatch.find('.submit-button');
    const urlTreatDispatch = Routing.generate('dispatch_treat_request', {id: dispatchId}, true);
    InitModal($modalTreatDispatch, $submitTreatedDispatch, urlTreatDispatch);

    const $modalDeleteDispatch = $('#modalDeleteDispatch');
    const $submitDeleteDispatch = $('#submitDeleteDispatch');
    const urlDispatchDelete = Routing.generate('dispatch_delete', true);
    InitModal($modalDeleteDispatch, $submitDeleteDispatch, urlDispatchDelete);

    let $modalPrintDeliveryNote = $('#modalPrintDeliveryNote');
    let $submitPrintDeliveryNote = $modalPrintDeliveryNote.find('.submit');
    let urlPrintDeliveryNote = Routing.generate('delivery_note_dispatch', {dispatch: dispatchId}, true);
    InitModal($modalPrintDeliveryNote, $submitPrintDeliveryNote, urlPrintDeliveryNote, {
        success: ({attachmentId}) => {
            AJAX.route(`GET`, `print_delivery_note_dispatch`, {
                dispatch: dispatchId,
                attachment: attachmentId,
            }).file({
                success: "Votre bon de livraison a bien été imprimée.",
                error: "Erreur lors de l'impression du bon de livraison."
            }).then(() => window.location.reload());
        },
        validator: forbiddenPhoneNumberValidator,
    });

    let $modalPrintWaybill = $('#modalPrintWaybill');
    let $submitPrintWayBill = $modalPrintWaybill.find('.submit');
    let urlPrintWaybill = Routing.generate('post_dispatch_waybill', {dispatch: dispatchId}, true);
    InitModal($modalPrintWaybill, $submitPrintWayBill, urlPrintWaybill, {
        success: ({attachmentId}) => {
            AJAX.route(`GET`, `print_waybill_dispatch`, {
                dispatch: dispatchId,
                attachment: attachmentId,
            }).file({
                success: "Votre lettre de voiture a bien été imprimée.",
                error: "Erreur lors de l'impression de la lettre de voiture."
            }).then(() => window.location.reload());
        },
        validator: forbiddenPhoneNumberValidator,
    });

    let $modalEditReference = $('#modalEditReference');
    Form.create($modalEditReference).onSubmit((data, form) => {
        form.loading(() => {
            return AJAX
                .route(AJAX.POST, `dispatch_form_reference`)
                .json(data)
                .then((response) => {
                    if (response.success) {
                        $modalEditReference.modal('hide');
                        loadDispatchReferenceArticle();
                        packsTable.ajax.reload();
                    }
                })
        });
    });

    let $modalAddReference = $('#modalAddReference');
    Form.create($modalAddReference).onSubmit((data, form) => {
        form.loading(() => {
            return AJAX
                .route(AJAX.POST, `dispatch_form_reference`)
                .json(data)
                .then((response) => {
                    if(response.success) {
                        $modalAddReference.modal('hide');
                        loadDispatchReferenceArticle();
                        if ($('.logistic-units-container').exists()) {
                            packsTable.ajax.reload();
                        } else {
                            window.location.reload();
                        }
                    }
                })
        });
    });

    let $modalAddUl = $('#modalAddLogisticUnit');
    Form.create($modalAddUl).onSubmit((data, form) => {
        form.loading(() => {
            data.set('fromModal', true);
            return AJAX
                .route(AJAX.POST, `dispatch_new_pack`, {dispatch: $('#dispatchId').val()})
                .json(data)
                .then((response) => {
                    if(response.success) {
                        $modalAddUl.modal('hide');
                        window.location.reload();
                    }
                })
        });
    });


    const queryParams = GetRequestQuery();
    const {'print-delivery-note': printDeliveryNote} = queryParams;
    if(Number(printDeliveryNote)) {
        delete queryParams['print-delivery-note'];
        SetRequestQuery(queryParams);
        $('#generateDeliveryNoteButton').click();
    }
});

function generateOverconsumptionBill($button, dispatchId) {
    $.post(Routing.generate('generate_overconsumption_bill', {dispatch: dispatchId}), {}, function(data) {
        $('button[name="newPack"]').addClass('d-none');

        packsTable.destroy();
        packsTable = initializePacksTable(dispatchId, data.modifiable);

        AJAX.route(`GET`, `print_overconsumption_bill`, {dispatch: dispatchId})
            .file({
                success: "Votre bon de surconsommation a bien été imprimé.",
                error: "Erreur lors de l'impression du bon de surconsommation."
            })
            .then(() => window.location.reload())
    });
}

function forbiddenPhoneNumberValidator($modal) {
    const $inputs = $modal.find(".forbidden-phone-numbers");
    const $invalidElements = [];
    const errorMessages = [];
    const numbers = ($('#forbiddenPhoneNumbers').val() || '')
        .split(';')
        .map((number) => number.replace(/[^0-9]/g, ''));

    $inputs.each(function() {
        const $input = $(this);
        const rawValue = ($input.val() || '');
        const value = rawValue.replace(/[^0-9]/g, '');

        if(value
            && numbers.indexOf(value) !== -1) {
            errorMessages.push(`Le numéro de téléphone ${rawValue} ne peut pas être utilisé ici`);
            $invalidElements.push($input);
        }
    });

    return {
        success: $invalidElements.length === 0,
        errorMessages,
        $isInvalidElements: $invalidElements,
    };
}

function openValidateDispatchModal() {
    const modalSelector = '#modalValidateDispatch';
    const $modal = $(modalSelector);

    clearModal(modalSelector);

    $modal.modal('show');
}

function openAddReferenceModal($button, options = {}) {
    const $modal = $('#modalAddReference');
    const dispatchId = $('#dispatchId').val();
    const $modalbody = $modal.find('.modal-body')
    const pack = options['unitId'] ?? null;
    wrapLoadingOnActionButton($button, () => {
        return AJAX
            .route(AJAX.GET, 'dispatch_add_reference_api', {dispatch: dispatchId, pack: pack})
            .json()
            .then((data)=>{
                $modalbody.html(data);
                $modal.modal('show');
                const selectPack = $modalbody.find('select[name=pack]');
                selectPack.on('change', function () {
                    const defaultQuantity = $(this).find('option:selected').data('default-quantity');
                    $modalbody.find('input[name=quantity]').val(defaultQuantity);
                })
                selectPack.trigger('change')
            })
    })
}

function openTreatDispatchModal() {
    const modalSelector = '#modalTreatDispatch';
    const $modal = $(modalSelector);

    clearModal(modalSelector);

    $modal.modal('show');
}

function runDispatchPrint() {
    const dispatchId = $('#dispatchId').val();
    $.get({
        url: Routing.generate('get_dispatch_packs_counter', {dispatch: dispatchId}),
    })
        .then(function({packsCounter}) {
            if(!packsCounter) {
                showBSAlert('Vous ne pouvez pas imprimer un acheminement sans colis', 'danger');
            } else {
                window.location.href = Routing.generate('print_dispatch_state_sheet', {dispatch: dispatchId});
            }
        });
}

function openDeliveryNoteModal($button) {
    const dispatchId = $button.data('dispatch-id');
    $
        .get(Routing.generate('api_delivery_note_dispatch', {dispatch: dispatchId}))
        .then((result) => {
            if(result.success) {
                const $modal = $('#modalPrintDeliveryNote');
                const $modalBody = $modal.find('.modal-body');
                $modalBody.html(result.html);
                $modal.modal('show');
            } else {
                showBSAlert(result.msg, "danger");
            }
        });
}

function openWaybillModal($button) {
    const dispatchId = $button.data('dispatch-id');

    Promise.all([
        $.get(Routing.generate('check_dispatch_waybill', {dispatch: dispatchId})),
        $.get(Routing.generate('api_dispatch_waybill', {dispatch: dispatchId})),
    ]).then((values) => {
        let check = values[0];
        if(!check.success) {
            showBSAlert(check.msg, "danger");
            return;
        }

        let result = values[1];
        if(result.success) {
            const $modal = $('#modalPrintWaybill');
            const $modalBody = $modal.find('.modal-body');
            $modalBody.html(result.html);
            $modal.modal('show');
        } else {
            showBSAlert(result.msg, "danger");
        }
    });
}

function copyTo($button, inputSourceName, inputTargetName) {
    const $modal = $button.closest('.modal');
    const $source = $modal.find(`[name="${inputSourceName}"]`);
    const $target = $modal.find(`[name="${inputTargetName}"]`);
    const valToCopy = $source.val();
    if($target.is('textarea')) {
        $target.text(valToCopy);
    } else {
        $target.val(valToCopy);
    }
}

function reverseFields($button, inputName1, inputName2) {
    const $modal = $button.closest('.modal');
    const $field1 = $modal.find(`[name="${inputName1}"]`);
    const $field2 = $modal.find(`[name="${inputName2}"]`);
    const val1 = $field1.val();
    const val2 = $field2.val();
    $field1.val(val2);
    $field2.val(val1);
}

function savePackLine(dispatchId, $row, async = true) {
    let data = Form.process($row);
    data = data instanceof FormData ? data.asObject() : data;

    if(data) {
        if (!jQuery.deepEquals(data, JSON.parse($row.data(`data`)))) {
            $.ajax({
                type: `POST`,
                url: Routing.generate(`dispatch_new_pack`, {dispatch: dispatchId}),
                data,
                async,
                success: response => {
                    $row.find(`.delete-pack-row`).data(`id`, response.id);
                    if(!response.success) {
                        showBSAlert(response.msg, `danger`);
                    }

                    $row.data(`data`, JSON.stringify(data));
                },
            });

            return true;
        }
    } else {
        $row.find('.is-invalid').first().trigger('focus');
        return true;
    }

    return false;
}

function initializePacksTable(dispatchId, isEdit) {
    const $table = $(`#packTable`);
    const table = initDataTable($table, {
        serverSide: false,
        ajax: {
            type: "GET",
            url: Routing.generate('dispatch_pack_api', {dispatch: dispatchId}, true),
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        domConfig: {
            removeInfo: true,
        },
        ordering: !isEdit,
        paging: false,
        searching: false,
        scrollY: false,
        scrollX: true,
        drawCallback: () => {
            $(`#packTable_wrapper`).css(`overflow-x`, `scroll`);
            $(`.dataTables_scrollBody, .dataTables_scrollHead`)
                .css('overflow', 'visible')
                .css('overflow-y', 'visible');

            const $rows = $(table.rows().nodes());

            $rows.each(function() {
                const $row = $(this);
                const data = Form.process($row, {
                    ignoreErrors: true,
                });

                $row.data(`data`, JSON.stringify(data instanceof FormData ? data.asObject() : data));
            })

            $rows.off(`focusout.keyboardNavigation`).on(`focusout.keyboardNavigation`, function(event) {
                const $row = $(this);
                const $target = $(event.target);
                const $relatedTarget = $(event.relatedTarget);

                const wasPackSelect = $target.closest(`td`).find(`select[name="pack"]`).exists();
                if ((event.relatedTarget && $.contains(this, event.relatedTarget))
                    || $relatedTarget.is(`button.delete-pack-row`)
                    || wasPackSelect) {
                    return;
                }

                savePackLine(dispatchId, $row);
            });
            if(isEdit) {
                scrollToBottom();
            }
            if (!$table.data('initialized')) {
                $table.data('initialized', true);
                // Resize table to avoid bug related to WIIS-8276,
                // timeout is necessary because drawCallback doesnt seem to be called when everything is fully loaded,
                // because we have some custom rendering actions which may take more time than native datatable rendering
                setTimeout(() => {
                    $table.DataTable().columns.adjust().draw();
                }, 500);
            }
        },
        createdRow: (row, data) => {
            // we display only + td on this line
            if (data && data.createRow) {
                const $row = $(row);
                const $tds = $row.children();
                const $tdAction = $tds.first();
                const $tdOther = $tds.slice(1);

                $tdAction
                    .attr('colspan', $tds.length)
                    .addClass('add-pack-row');
                $tdOther.addClass('d-none');
            }
        },
        columnDefs: [
            {targets: 1, width: '300px'},
        ],
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: 'code', name: 'code', title: Translation.of('Demande', 'Acheminements', 'Général', 'Code')},
            {data: 'quantity', name: 'quantity', title: Translation.of('Demande', 'Acheminements', 'Général', 'Quantité à acheminer') + (isEdit ? '*' : '')},
            {data: 'nature', name: 'nature', title: Translation.of('Demande','Acheminements', 'Général', 'Nature') + (isEdit ? '*' : '')},
            {data: 'weight', name: 'weight', title: Translation.of('Demande', 'Acheminements', 'Général', 'Poids (kg)')},
            {data: 'volume', name: 'volume', title: Translation.of('Demande', `Acheminements`, `Général`, 'Volume (m3)')},
            {data: 'comment', name: 'comment', title: Translation.of('Général', null, 'Modale', 'Commentaire')},
            {data: 'lastMvtDate', name: 'lastMvtDate', title: Translation.of('Demande', 'Acheminements', 'Général', 'Date dernier mouvement'), render: function(data, type) {
                if(type !== `sort`) {
                    const date = moment(data, 'YYYY/MM/DD HH:mm');
                    if(date.isValid()) {
                        const $userFormat = $('#userDateFormat');
                        const format = ($userFormat.val() ? DATE_FORMATS_TO_DISPLAY[$userFormat.val()] : 'DD/MM/YYYY') + ' HH:mm';
                        return date.format(format);
                    }
                }

                return data;
            }},
            {data: 'lastLocation', name: 'lastLocation', title: Translation.of('Demande', `Acheminements`, `Général`, `Dernier emplacement`)},
            {data: 'operator', name: 'operator', title: Translation.of('Demande', `Acheminements`, `Général`, `Opérateur`)},
            {data: 'status', name: 'status', title: Translation.of('Demande', `Général`, `Statut`)},
        ],
    });

    if(isEdit) {
        scrollToBottom();

        WysiwygManager.initializeOneLineWYSIWYG($table);

        $table.on(`keydown`, `[name="quantity"]`, function(event) {
            if(event.key === `.` || event.key === `,` || event.key === `-` || event.key === `+` || event.key === `e`) {
                event.preventDefault();
                event.stopPropagation();
            }
        });

        $table.on(`input`, `[name="weight"], [name="volume"]`, function(event) {
            const value = event.target.value;
            const digits = value.split('.')[1];
            if(digits && digits.length > 3) {
                $(event.target).val(Math.floor(value * 1000) / 1000)
                document.execCommand(`undo`);
            }
        });

        $table.on(`change`, `select[name="pack"]`, function() {
            const $select = $(this);
            const $row = $select.closest(`tr`);
            const $quantity = $row.find(`input[name=quantity]`);
            const $nature = $row.find(`[name=nature]`);

            const [value] = $select.select2(`data`);
            // only for existing logistic unit
            // for new logistic unit it will be undefined, the quantity field is directly filled
            const defaultQuantity = value.defaultQuantityForDispatch;

            let code = value.text || '';
            const packPrefix = $select.data('search-prefix');
            if(packPrefix && !code.startsWith(packPrefix)) {
                code = `${packPrefix}${code}`;
            }

            $row.removeClass(`focus-within`);
            $select.closest(`td, th`)
                .empty()
                .append(`<span title="${code}">${code}</span> <input type="hidden" name="pack" class="data" value="${code}"/>`);
            $row.find(`.d-none`).removeClass(`d-none`);
            $row.find(`[name=weight]`).val(value.weight);
            $row.find(`[name=volume]`).val(value.volume);
            $row.find(`[name=comment]`).val(value.stripped_comment);
            $row.find(`.lastMvtDate`).text(value.lastMvtDate);
            $row.find(`.lastLocation`).text(value.lastLocation);
            $row.find(`.operator`).text(value.operator);
            $row.find(`.status`).text(Translation.of('Demande', 'Acheminements', 'Général', 'À traiter', false));

            if (defaultQuantity !== undefined) {
                $quantity.val(defaultQuantity);
            }

            if(value.nature_id && value.nature_label) {
                $nature.val(value.nature_id);
            }

            table.columns.adjust().draw();

            if ($quantity.val() && $nature.val()) {
                // trigger dispatch pack saving if nature and pack filled
                $quantity.trigger('focusout.keyboardNavigation');
            }
            else {
                $quantity.trigger('focus');
            }
        });

        $table.on(`click`, `.add-pack-row`, function() {
            addPackRow(table, $(this));
        });

        $table.on(`keydown`, `[data-wysiwyg="comment"]`, function(event) {
            const tabulationKeyCode = 9;
            if(event.keyCode === tabulationKeyCode) {
                event.preventDefault();
                event.stopPropagation();

                const $nextRow = $(this).closest(`tr`).next();
                if($nextRow.find(`.add-pack-row`).exists()) {
                    addPackRow(table, $(`.add-pack-row`));
                } else if($nextRow.find(`select[name=pack]`).exists()) {
                    $nextRow.find(`select[name=pack]`).select2(`open`);
                } else {
                    $nextRow.find(`[name=quantity]`).focus();
                }
            }
        });
    }

    let $modalDeletePack = $('#modalDeletePack');
    let $submitDeletePack = $('#submitDeletePack');
    $table.on(`click`, `.delete-pack-row`, function() {
        $modalDeletePack.modal(`show`);

        $submitDeletePack.off(`click.deleteRow`).on(`click.deleteRow`, () => {
            const data = JSON.stringify({
                pack: $(this).data(`id`) || null,
            });

            $.post(Routing.generate('dispatch_delete_pack', true), data, response => {
                table.row($(this).closest(`tr`))
                    .remove()
                    .draw();

                showBSAlert(response.msg, response.success ? `success` : `danger`)
            });
        });
    });

    $(window).on(`beforeunload`, () =>  {
        const $focus = $(`tr :focus`);
        if($focus.exists()) {
            if(savePackLine(dispatchId, $focus.closest(`tr`), false)) {
                return true;
            }
        }
    });

    return table;
}

function addPackRow(table, $button) {
    const $table = $button.closest('table');
    const $isInvalid = $table.find('.is-invalid');

    if ($isInvalid.length === 0) {
        const row = table.row($button.closest(`tr`));
        const data = row.data();

        row.remove();
        table.row.add(JSON.parse($(`#newPackRow`).val()));
        table.row.add(data);
        table.draw();

        scrollToBottom();
    }
}

function scrollToBottom() {
    window.scrollTo(0, document.body.scrollHeight);
}

function getStatusHistory(dispatch) {
    return $.get(Routing.generate(`dispatch_status_history_api`, {dispatch}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.status-history-container`);
            $statusHistoryContainer.html(template);
        });
}

function loadDispatchReferenceArticle({start, search} = {}) {
    start = start || 0;
    const $logisticUnitsContainer = $('.logistic-units-container');
    const dispatch = $('#dispatchId').val();

    const params = {dispatch, start};
    if (search) {
        params.search = search;
    }
    else {
        clearPackListSearching();
    }

    wrapLoadingOnActionButton(
        $logisticUnitsContainer,
        () => (
            AJAX.route('GET', 'dispatch_pack_api', params)
                .json()
                .then(data => {
                    $logisticUnitsContainer.html(data.html);
                    $logisticUnitsContainer.find('.reference-articles-container table')
                        .each(function() {
                            const $table = $(this);
                            initDataTable($table, {
                                serverSide: false,
                                ordering: true,
                                paging: false,
                                searching: false,
                                order: [['reference', "desc"]],
                                columns: [
                                    {data: 'actions', className: 'noVis hideOrder', orderable: false},
                                    {data: 'reference', title: 'Référence'},
                                    {data: 'quantity', title: 'Quantité'},
                                    {data: 'batchNumber', title: 'N° de lot'},
                                    {data: 'manufacturerCode', title: 'Code fabriquant'},
                                    {data: 'sealingNumber', title: 'N° de plombage / scellée'},
                                    {data: 'ADR', title: 'ADR'},
                                    {data: 'serialNumber', title: 'N° de série'},
                                    {data: 'volume', title: 'Volume (m3)'},
                                    {data: 'weight', title: 'Poids (kg)'},
                                    {data: 'outFormatEquipment', title: 'Matériel hors format'},
                                    {data: 'associatedDocumentTypes', title: 'Types de documents associés'},
                                    {data: 'comment', title: 'Commentaire', orderable: false},
                                    {data: 'attachments', title: 'Photos', orderable: false},
                                ],
                                domConfig: {
                                    removeInfo: true,
                                    needsPaginationRemoval: true,
                                    removeLength: true,
                                    removeTableHeader: true,
                                },
                                rowConfig: {
                                    needsRowClickAction: true,
                                    needsColor: true,
                                },
                            });
                        });

                    $logisticUnitsContainer
                        .find('.paginate_button:not(.disabled)')
                        .on('click', function() {
                            const $button = $(this);
                            loadDispatchReferenceArticle({
                                start: $button.data('page'),
                                search: search
                            });
                        });
                })
        )
    )
}

function launchPackListSearching() {
    const $logisticUnitsContainer = $('.logistic-units-container');
    const $searchInput = $logisticUnitsContainer
        .closest('.content')
        .find('input[type=search]');

    $searchInput.on('input', function () {
        const $input = $(this);
        const referenceArticleSearch = $input.val();
        loadReceptionLines({search: referenceArticleSearch});
    });
}

function clearPackListSearching() {
    const $logisticUnitsContainer = $('.logistic-units-container');
    const $searchInput = $logisticUnitsContainer
        .closest('.content')
        .find('input[type=search]');
    $searchInput.val(null);
}

function refArticleChanged($select) {
    if (!$select.data(`select2`)) {
        return;
    }

    const selectedReference = $select.select2(`data`);
    let $modalAddReference = $("#modalAddReference");

    if (selectedReference.length > 0) {
        const description = selectedReference[0]["description"] || [];

        $modalAddReference.find(`input[name=outFormatEquipment][value='${description["outFormatEquipment"] || 0}']`).prop('checked', true);
        $modalAddReference.find("[name=manufacturerCode]").val(description["manufacturerCode"]);
        $modalAddReference.find("input[name=height]").val(description["height"]);
        $modalAddReference.find("[name=weight]").val(description["weight"]);
        $modalAddReference.find("[name=volume]").val(description["volume"]).prop("disabled", true);
        const associatedDocumentTypes = description["associatedDocumentTypes"] ? description["associatedDocumentTypes"].split(',') : [];
        const $associatedDocumentTypesSelect = $modalAddReference.find("[name=associatedDocumentTypes]");
        $associatedDocumentTypesSelect
            .val(associatedDocumentTypes)
            .trigger('change');
    }
}

function deleteRefArticle(dispatchReferenceArticle) {
    Modal.confirm({
        ajax: {
            method: 'DELETE',
            route: 'dispatch_delete_reference',
            params: {dispatchReferenceArticle},
        },
        message: 'Voulez-vous réellement supprimer cette référence article ?',
        title: 'Supprimer la référence article',
        validateButton: {
            color: 'danger',
            label: 'Supprimer'
        },
    });
}


function openAddLogisticUnitModal() {
    const $modalAddUl = $('#modalAddLogisticUnit');
    const dispatchId = $('#dispatchId').val();
    const $modalbody = $modalAddUl.find('.modal-body')

    AJAX.route(AJAX.GET, 'dispatch_add_logistic_unit_api', {dispatch: dispatchId})
        .json()
        .then((data)=>{
            $modalbody.html(data);
            $modalAddUl.modal('show');
        });
}

function selectUlChanged($select){
    const $modal = $('#modalAddLogisticUnit');
    const ulData = $select.select2('data')[0];
    console.log(ulData);
    if (ulData) {
        const defaultNatureId = ulData.nature_id || $modal.find('[name=defaultNatureId]').val();
        const defaultNatureLabel = ulData.nature_label || $modal.find('[name=defaultNatureLabel]').val();
        const defaultQuantityNatureForDispatch = ulData.nature_default_quantity_for_dispatch || $modal.find('[name=defaultQuantityNatureForDispatch]').val();

        const ulLastMvtDate = $modal.find('.ul-last-movement-date');
        const ulLastLocation = $modal.find('.ul-last-location');
        const ulOperator = $modal.find('.ul-operator');
        const ulNature = $modal.find('select[name=nature]');
        const ulQuantity = $modal.find('[name=quantity]');

        ulLastMvtDate.text(ulData.lastMvtDate || '-');
        ulLastLocation.text(ulData.lastLocation || '-');
        ulQuantity.val(defaultQuantityNatureForDispatch);
        ulOperator.text(ulData.operator || '-');
        if (defaultNatureId && defaultNatureLabel) {
            let newOption = new Option(defaultNatureLabel, defaultNatureId, true, true);
            ulNature.append(newOption).trigger('change');
        }
        $modal.find('[name=packID]').val(ulData.exists ? ulData.id : null);
    }
}
