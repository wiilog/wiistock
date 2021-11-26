$(function() {
    const dispatchId = $('#dispatchId').val();

    const packTable = initializePacksTable(dispatchId, $(`#isEdit`).val(), $(`#packPrefix`).val());

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

    let $modalDeletePack = $('#modalDeletePack');
    let $submitDeletePack = $('#submitDeletePack');
    let urlDeletePack = Routing.generate('dispatch_delete_pack', true);
    InitModal($modalDeletePack, $submitDeletePack, urlDeletePack, {tables: [packTable]});

    let $modalPrintDeliveryNote = $('#modalPrintDeliveryNote');
    let $submitPrintDeliveryNote = $modalPrintDeliveryNote.find('.submit');
    let urlPrintDeliveryNote = Routing.generate('delivery_note_dispatch', {dispatch: $('#dispatchId').val()}, true);
    InitModal($modalPrintDeliveryNote, $submitPrintDeliveryNote, urlPrintDeliveryNote, {
        success: ({attachmentId}) => {
            window.location.href = Routing.generate('print_delivery_note_dispatch', {
                dispatch: $('#dispatchId').val(),
                attachment: attachmentId,
            });
        },
        validator: forbiddenPhoneNumberValidator,
    });

    let $modalPrintWaybill = $('#modalPrintWaybill');
    let $submitPrintWayBill = $modalPrintWaybill.find('.submit');
    let urlPrintWaybill = Routing.generate('post_dispatch_waybill', {dispatch: $('#dispatchId').val()}, true);
    InitModal($modalPrintWaybill, $submitPrintWayBill, urlPrintWaybill, {
        success: ({attachmentId}) => {
            window.location.href = Routing.generate('print_waybill_dispatch', {
                dispatch: $('#dispatchId').val(),
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

    $(document).on(`click`, `.delete-pack-row`, function() {
        $modalDeletePack.modal(`show`);
        $submitDeletePack.attr(`value`, $(this).data(`id`));
    });
});

function generateOverconsumptionBill(dispatchId) {
    $.post(Routing.generate('generate_overconsumption_bill', {dispatch: dispatchId}), {}, function(data) {
        $('.zone-entete').html(data.entete);
        $('.zone-entete [data-toggle="popover"]').popover();
        $('button[name="newPack"]').addClass('d-none');

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

function togglePackDetails(emptyDetails = false) {
    const $modal = $('#modalPack');
    const packCode = $modal.find('[name="pack"]').val();
    const prefix = $modal.find(`.pack-code-prefix`).val() || ``;

    $modal.find('.pack-details').addClass('d-none');
    $modal.find('.spinner-border').removeClass('d-none');
    $modal.find('.error-msg').empty();

    const $natureField = $modal.find('[name="nature"]');
    $natureField.val(null).trigger('change');
    const $quantityField = $modal.find('[name="quantity"]');
    $quantityField.val(null);
    const $packQuantityField = $modal.find('[name="pack-quantity"]');
    $packQuantityField.val(null);
    const $weightField = $modal.find('[name="weight"]');
    $weightField.val(null);
    const $volumeField = $modal.find('[name="volume"]');
    $volumeField.val(null);
    const $commentField = $modal.find('.ql-editor');
    $commentField.html(null);

    if(packCode && !emptyDetails) {
        $.get(Routing.generate('get_pack_intel', {packCode: prefix + packCode}))
            .then(({success, pack}) => {
                if(success) {
                    if(pack.nature) {
                        $natureField.val(pack.nature.id).trigger('change');
                    }
                    if(pack.quantity || pack.quantity === 0) {
                        $quantityField.val(pack.quantity);
                        $packQuantityField.val(pack.quantity);
                        $weightField.val(pack.weight);
                        $volumeField.val(pack.volume);
                    }

                    $commentField.html(pack.comment);
                }

                $modal.find('.pack-details').removeClass('d-none');
                $modal.find('.spinner-border').addClass('d-none');
            })
            .catch(() => {
                $modal.find('.pack-details').removeClass('d-none');
                $modal.find('.spinner-border').addClass('d-none');
            });
    } else {
        $modal.find('.spinner-border').addClass('d-none');
        if(packCode || emptyDetails) {
            $modal.find('.pack-details').removeClass('d-none');
        }
    }
    setTimeout(function() {
        $('input[name="pack"]').focus();
    }, 500);
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

function initializePacksTable(dispatchId, isEdit, packPrefix) {
    const $table = $(`#packTable`);
    const table = initDataTable($table, {
        ajax: {
            type: "GET",
            url: Routing.generate('dispatch_pack_api', {dispatch: dispatchId, edit: isEdit}, true),
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
        scrollX: false,
        drawCallback: () => {
            $(`.dataTables_scrollBody, .dataTables_scrollHead`)
                .css('overflow', '')
                .css('overflow-y', 'visible')
                .css('margin-right', '15px');

            const $rows = $(table.rows().nodes());

            $rows.off(`focusout.keyboardNavigation`).on(`focusout.keyboardNavigation`, function(event) {
                const $row = $(this);
                const target = event.relatedTarget;
                const wasPackSelect = $(event.target).closest(`td`).find(`select[name="pack"]`).exists();
                if(target && $.contains(this, target) || $(event.relatedTarget).is(`button`) || wasPackSelect) {
                    return;
                }

                const data = Form.process($row);
                if(data) {
                    const route = Routing.generate(`dispatch_new_pack`, {dispatch: dispatchId});
                    $.post(route, data.asObject(), function(response) {
                        $row.find(`.delete-pack-row`).data(`id`, response.id);
                        showBSAlert(response.msg, response.success ? `success` : `danger`);
                    });
                }
            });
        },
        columnDefs: [
            {targets: 1, width: '300px'},
        ],
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
            {data: 'code', name: 'code', title: 'Code'},
            {data: 'quantity', name: 'quantity', title: 'acheminement.Quantité à acheminer', translated: true},
            {data: 'nature', name: 'nature', title: 'natures.nature', translated: true},
            {data: 'weight', name: 'weight', title: 'Poids (kg)'},
            {data: 'volume', name: 'volume', title: 'Volume (m3)'},
            {data: 'comment', name: 'comment', title: 'Commentaire'},
            {data: 'lastMvtDate', name: 'lastMvtDate', title: 'Date dernier mouvement'},
            {data: 'lastLocation', name: 'lastLocation', title: 'Dernier emplacement'},
            {data: 'operator', name: 'operator', title: 'Opérateur'},
            {data: 'status', name: 'status', title: 'Statut'},
        ],
    });

    if(isEdit) {
        scrollToBottom();

        // TODO: décommenter pour la WIIS-6177
        // Form.initializeWYSIWYG($table);

        $table.on(`change`, `select[name="pack"]`, function() {
            const $select = $(this);
            const $row = $select.closest(`tr`);
            const value = $select.select2(`data`)[0];

            let code = value.text;
            if(packPrefix && !value.startsWith(packPrefix)) {
                code = `${packPrefix}-${value}`;
            }

            $select.closest(`td, th`)
                .empty()
                .append(`<span title="${code}">${code}</span> <input type="hidden" name="pack" class="data" value="${code}"/>`);
console.log(value);
            $row.find(`.d-none`).removeClass(`d-none`);
            $row.find(`[name=quantity]`).val(value.quantity).focus();
            $row.find(`[name=weight]`).val(value.weight);
            $row.find(`[name=volume]`).val(value.volume);
            $row.find(`[name=comment]`).val(value.stripped_comment);
            $row.find(`.lastMvtDate`).text(value.lastMvtDate);
            $row.find(`.lastLocation`).text(value.lastLocation);
            $row.find(`.operator`).text(value.operator);
            $row.find(`.status`).text(`À traiter`);
            if(value.nature_id && value.nature_label) {
                $row.find(`[name=nature]`).append(new Option(value.nature_label, value.nature_id, true, true)).trigger('change');
            }
        });

        $table.on(`click`, `.add-pack-row`, function() {
            addPackRow(table, $(this));
        });

        $table.on(`keydown`, `[name="comment"]`, function(event) {
            if(event.keyCode === 9) {
                event.preventDefault();
                event.stopPropagation();

                addPackRow(table, $(`.add-pack-row`));
            }
        });

        if(packPrefix && packPrefix.length) {
            $table.arrive(`.select2-search`, function() {
                const $container = $(this);
                $container.addClass(`d-flex`);
                $container.prepend(`
                    <input class="search-prefix" name="searchPrefix" size=${packPrefix.length} value="${packPrefix}" disabled/>
                `);
            })
        }
    }

    return table;
}

function addPackRow(table, $button) {
    const row = table.row($button.closest(`tr`));
    const data = row.data();

    row.remove();
    table.row.add(JSON.parse($(`#newPackRow`).val()));
    table.row.add(data);
    table.draw();

    scrollToBottom();
}

function scrollToBottom() {
    window.scrollTo(0, document.body.scrollHeight);
}
