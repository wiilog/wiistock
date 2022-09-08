let packsTable;

$(function() {
    const dispatchId = $('#dispatchId').val();
    const isEdit = $(`#isEdit`).val();

    packsTable = initializePacksTable(dispatchId, isEdit);

    const $modalEditDispatch = $('#modalEditDispatch');
    const $submitEditDispatch = $('#submitEditDispatch');
    const urlDispatchEdit = Routing.generate('dispatch_edit', true);
    InitModal($modalEditDispatch, $submitEditDispatch, urlDispatchEdit);

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
            window.location.href = Routing.generate('print_delivery_note_dispatch', {
                dispatch: dispatchId,
                attachment: attachmentId,
            });
        },
        validator: forbiddenPhoneNumberValidator,
    });

    let $modalPrintWaybill = $('#modalPrintWaybill');
    let $submitPrintWayBill = $modalPrintWaybill.find('.submit');
    let urlPrintWaybill = Routing.generate('post_dispatch_waybill', {dispatch: dispatchId}, true);
    InitModal($modalPrintWaybill, $submitPrintWayBill, urlPrintWaybill, {
        success: ({attachmentId}) => {
            window.location.href = Routing.generate('print_waybill_dispatch', {
                dispatch: dispatchId,
                attachment: attachmentId,
            });
        },
        validator: forbiddenPhoneNumberValidator,
    });

    const queryParams = GetRequestQuery();
    const {'print-delivery-note': printDeliveryNote} = queryParams;
    if(Number(printDeliveryNote)) {
        delete queryParams['print-delivery-note'];
        SetRequestQuery(queryParams);
        $('#generateDeliveryNoteButton').click();
    }
});

function generateOverconsumptionBill(dispatchId) {
    $.post(Routing.generate('generate_overconsumption_bill', {dispatch: dispatchId}), {}, function(data) {
        $('.zone-entete').html(data.entete);
        $('.zone-entete [data-toggle="popover"]').popover();
        $('button[name="newPack"]').addClass('d-none');

        packsTable.destroy();
        packsTable = initializePacksTable(dispatchId, data.modifiable);

        Wiistock.download(Routing.generate('print_overconsumption_bill', {dispatch: dispatchId}));
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
            {data: 'code', name: 'code', title: 'Code'},
            {data: 'quantity', name: 'quantity', title: Translation.of(`Acheminements`, `Détails acheminement - Liste des unités logistiques`, `Quantité à acheminer`) + (isEdit ? '*' : ''), tooltip: 'Quantité à acheminer'},
            {data: 'nature', name: 'nature', title: Translation.of(`Acheminements`, `Détails acheminement - Liste des unités logistiques`, `Nature`) + (isEdit ? '*' : ''), tooltip: 'nature'},
            {data: 'weight', name: 'weight', title: 'Poids (kg)'},
            {data: 'volume', name: 'volume', title: 'Volume (m3)'},
            {data: 'comment', name: 'comment', title: 'Commentaire'},
            {data: 'lastMvtDate', name: 'lastMvtDate', title: 'Date dernier mouvement', render: function(data, type) {
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
            {data: 'lastLocation', name: 'lastLocation', title: 'Dernier emplacement'},
            {data: 'operator', name: 'operator', title: 'Opérateur'},
            {data: 'status', name: 'status', title: 'Statut'},
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
            const [value] = $select.select2(`data`);

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
            $row.find(`.status`).text(`À traiter`);

            if(value.nature_id && value.nature_label) {
                $row.find(`[name=nature]`).val(value.nature_id).trigger(`change`);
            }

            table.columns.adjust().draw();
            $row.find(`[name=quantity]`).focus();
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
