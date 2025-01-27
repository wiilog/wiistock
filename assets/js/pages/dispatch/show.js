import AJAX, {DELETE, GET, POST} from "@app/ajax";
import Form from "@app/form";
import Routing from '@app/fos-routing';
import Camera from "@app/camera";
import Flash from "@app/flash";
import {wrapLoadingOnActionButton} from "@app/loading";
import {computeDescriptionFormValues} from "@app/pages/reference-article/common";
import {clearPackListSearching} from "@app/pages/pack/common";
import Modal from "@app/modal";

let packsTable;

global.generateShipmentNote = generateShipmentNote;
global.generateOverconsumptionBill = generateOverconsumptionBill;
global.generateDispatchLabel = generateDispatchLabel;
global.openValidateDispatchModal = openValidateDispatchModal;
global.openAddReferenceModal = openAddReferenceModal;
global.openTreatDispatchModal = openTreatDispatchModal;
global.runDispatchPrint = runDispatchPrint;
global.openWaybillModal = openWaybillModal;
global.copyTo = copyTo;
global.reverseFields = reverseFields;
global.refArticleChanged = refArticleChanged;
global.deleteRefArticle = deleteRefArticle;
global.openAddLogisticUnitModal = openAddLogisticUnitModal;
global.selectUlChanged = selectUlChanged;


$(function() {
    registerCopyToClipboard(`Le numéro a bien été copié dans le presse-papiers.`);
    const dispatchId = $('#dispatchId').val();
    const isEdit = $(`#isEdit`).val();

    if(!$('#packTable').exists()) {
        loadDispatchReferenceArticle();
    }
    getStatusHistory(dispatchId);
    packsTable = initializePacksTable(dispatchId, {modifiable: isEdit});

    const $modalValidateDispatch = $('#modalValidateDispatch');
    Form
        .create($modalValidateDispatch)
        .submitTo(AJAX.POST, `dispatch_validate_request`, {
            routeParams: {id: dispatchId},
            success: response => {
                if(response.success) {
                    window.location.reload()
                }
            },
        })

    const $modalTreatDispatch = $('#modalTreatDispatch');
    Form
        .create($modalTreatDispatch)
        .submitTo(AJAX.POST, `dispatch_treat_request`, {
            routeParams: {id: dispatchId},
            success: response => {
                if (response.success) {
                    window.location.reload()
                }
            },
        });

    const $modalEditDispatch = $('#modalEditDispatch');
    Form
        .create($modalEditDispatch)
        .on('change', '[name=customerName]', (event) => {
            const $customers = $(event.target)
            // pre-filling customer information according to the customer
            const [customer] = $customers.select2('data');
            $modalEditDispatch.find('[name=customerPhone]').val(customer?.phoneNumber);
            $modalEditDispatch.find('[name=customerRecipient]').val(customer?.recipient);
            $modalEditDispatch.find('[name=customerAddress]').val(customer?.address);
        })
        .onOpen(() => {
            Modal
                .load(
                    'dispatch_edit_api',
                    {id: dispatchId},
                    $modalEditDispatch,
                    $modalEditDispatch.find(`.modal-body`),
                    {
                        onOpen: () => {
                            const $userFormat = $('#userDateFormat');
                            const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
                            initDateTimePicker('.free-field-date', DATE_FORMATS_TO_DISPLAY[format]);
                            initDateTimePicker('.free-field-datetime', DATE_FORMATS_TO_DISPLAY[format] + ' HH:mm');
                            Camera
                                .init(
                                    $modalEditDispatch.find(`.take-picture-modal-button`),
                                    $modalEditDispatch.find(`[name="files[]"]`)
                                )
                        }
                    }
                );
        })
        .submitTo(
            AJAX.POST,
            'dispatch_edit',
            {
                success: () => {
                    window.location.reload()
                }
            }
        )

    const $modalDeleteDispatch = $('#modalDeleteDispatch');
    const $submitDeleteDispatch = $('#submitDeleteDispatch');
    const urlDispatchDelete = Routing.generate('dispatch_delete', true);
    InitModal($modalDeleteDispatch, $submitDeleteDispatch, urlDispatchDelete);

    let $modalPrintWaybill = $('#modalPrintWaybill');
    let $submitPrintWayBill = $modalPrintWaybill.find('.submit');
    let urlPrintWaybill = Routing.generate('post_dispatch_waybill', {dispatch: dispatchId}, true);
    InitModal($modalPrintWaybill, $submitPrintWayBill, urlPrintWaybill, {
        success: ({attachmentId, headerDetailsConfig}) => {
            $(`.zone-entete`).html(headerDetailsConfig);
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

    initDeliveryNoteModal(dispatchId);
    registerVolumeCompute();
});

function generateOverconsumptionBill($button, dispatchId) {
    $.post(Routing.generate('generate_overconsumption_bill', {dispatch: dispatchId}), {}, function(data) {
        $('button[name="newPack"]').addClass('d-none');

        packsTable.destroy();
        packsTable = initializePacksTable(dispatchId, data);

        AJAX.route(`GET`, `print_overconsumption_bill`, {dispatch: dispatchId})
            .file({
                success: "Votre bon de surconsommation a bien été imprimé.",
                error: "Erreur lors de l'impression du bon de surconsommation."
            })
            .then(() => window.location.reload())
    });
}

function generateDispatchLabel($button, dispatchId) {
    AJAX.route(`GET`, `print_dispatch_label`, {dispatch: dispatchId})
        .file({
            success: "Votre étiquette a bien été imprimée.",
            error: "Erreur lors de l'impression de l'étiquette"
        })
        .then(() => window.location.reload())
}

function generateShipmentNote($button, dispatchId) {
    AJAX.route(AJAX.POST, `generate_shipment_note`, {dispatch: dispatchId})
        .json()
        .then(({success}) => {
            if(success) {
                AJAX.route(AJAX.GET, `print_shipment_note`, {dispatch: dispatchId})
                    .file({
                        success: "Votre bon a bien été imprimé.",
                        error: "Erreur lors de l'impression du bon."
                    })
                    .then(() => window.location.reload());
            }
        });
}

function forbiddenPhoneNumberValidator($form, data = undefined, errors = undefined) {
    const $inputs = $form.find(".forbidden-phone-numbers");
    const numbers = ($('#forbiddenPhoneNumbers').val() || '')
        .split(';')
        .map((number) => number.replace(/[^0-9]/g, ''));

    const $invalidElements = [];
    const errorMessages = [];
    $inputs.each(function() {
        const $input = $(this);
        const rawValue = ($input.val() || '');
        const value = rawValue.replace(/[^0-9]/g, '');

        if(value && numbers.indexOf(value) !== -1) {
            const message = `Le numéro de téléphone ${rawValue} ne peut pas être utilisé ici.`;
            if(errors || data) {
                errors.push({
                    message,
                    global: true,
                });
            } else {
                errorMessages.push(message);
                $invalidElements.push($input);
            }
        }
    });

    return errors || {
        success: $invalidElements.length === 0,
        errorMessages,
        $isInvalidElements: $invalidElements,
    };
}

function openValidateDispatchModal() {
    const modalSelector = '#modalValidateDispatch';
    const $modal = $(modalSelector);

    $modal.find('select[name=status]').val(null).trigger('change');

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
            .then(({template, success})=>{
                if(success) {
                    $modalbody.html(template);
                    $modal.modal('show');
                    const selectPack = $modalbody.find('select[name=pack]');
                    selectPack.on('change', function () {
                        const defaultQuantity = $(this).find('option:selected').data('default-quantity');
                        $modalbody.find('input[name=quantity]').val(defaultQuantity);
                    })
                    selectPack.trigger('change');
                }
            })
    })
}

function openTreatDispatchModal() {
    const modalSelector = '#modalTreatDispatch';
    const $modal = $(modalSelector);

    $modal.find('select[name=status]').val(null).trigger('change');

    $modal.modal('show');
}

function runDispatchPrint($button) {
    const dispatchId = $('#dispatchId').val();

    wrapLoadingOnActionButton($button, () => (
        AJAX.route(`GET`, `get_dispatch_packs_counter`, {dispatch: dispatchId})
            .json()
            .then(({packsCounter}) => {
                if (!packsCounter) {
                    showBSAlert('Vous ne pouvez pas imprimer un acheminement sans UL', 'danger');
                } else {
                    AJAX.route(`GET`, `dispatch_note`, {dispatch: dispatchId})
                        .json()
                        .then(({success, headerDetailsConfig}) => {
                            if (success) {
                                $(`.zone-entete`).html(headerDetailsConfig);
                                AJAX.route(`GET`, `print_dispatch_state_sheet`, {dispatch: dispatchId})
                                    .file({
                                        success: "Votre bon d'acheminement a bien été imprimé.",
                                        error: "Erreur lors de l'impression du bon d'acheminement."
                                    })
                                    .then(() => window.location.reload());
                            }
                        });
                }
            })));
}

function initDeliveryNoteModal(dispatchId) {
    const $modal = $(`#modalPrintDeliveryNote`);
    const $modalBody = $modal.find(`.modal-body`);

    Form.create($modal)
        .addProcessor((data, errors, $form) => forbiddenPhoneNumberValidator($form, data, errors))
        .submitTo(AJAX.POST, `delivery_note_dispatch`, {
            success: ({attachmentId, headerDetailsConfig}) => {
                $(`.zone-entete`).html(headerDetailsConfig);

                Flash.presentFlashWhile(() => (
                    AJAX.route(`GET`, `print_delivery_note_dispatch`, {
                        dispatch: dispatchId,
                        attachment: attachmentId,
                    }).file({
                        success: "Votre bon de livraison a bien été téléchargé.",
                        error: "Erreur lors du téléchargement du bon de livraison."
                    }).then(() => window.location.reload())
                ), `Le téléchargement du bon de livraison est en cours, veuillez patienter...`);
            }
        })
        .onOpen(() => {
            Modal.load(`api_delivery_note_dispatch`, {dispatch: dispatchId, fromDelivery: false}, $modal, $modalBody, {
                onOpen: ({success, msg}) => {
                    if (success) {
                        $modal.find(`[name=buyer]`).on(`change`, function () {
                            const data = $(this).select2(`data`);
                            if (data.length > 0) {
                                const {fax, phoneNumber, address} = data[0];
                                const $modal = $(this).closest(`.modal`);
                                if (fax) {
                                    $modal.find(`input[name=buyerFax]`).val(fax);
                                }
                                if (phoneNumber) {
                                    $modal.find(`input[name=buyerPhone]`).val(phoneNumber);
                                }
                                if (address) {
                                    $modal.find(`[name=deliveryAddress],[name=invoiceTo],[name=soldTo],[name=endUser],[name=deliverTo]`).val(address);
                                }
                            }
                        });
                    } else {
                        Flash.add(Flash.ERROR, msg);
                    }
                },
                onClose: () => $modalBody.empty(),
            });
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

            $('select[name=receiverUsername]').on('change', function (){
                const data = $(this).select2('data');
                if(data.length > 0){
                    const {email, phoneNumber, address} = data[0];
                    const $modal = $(this).closest('.modal');
                    if(phoneNumber || email){
                        $modal.find('input[name=receiverEmail]').val(phoneNumber.concat(' - ', email));
                    }
                    if(address){
                        $modal.find('[name=receiver]').val(address);
                    }
                }
            });
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

function savePackLine(dispatchId,
                      $row,
                      async = true,
                      onSuccess = null) {
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
                    $row.find('[name=height]').trigger('change');

                    if (onSuccess) {
                        onSuccess();
                    }
                },
            });

            return true;
        }
    } else {
        return true;
    }

    return false;
}

function initializePacksTable(dispatchId, {modifiable, initialVisibleColumns}) {
    const $table = $(`#packTable`);
    const columns = $table.data('initial-visible') || (initialVisibleColumns ? JSON.parse(initialVisibleColumns) : undefined);

    const table = initDataTable($table, {
        serverSide: false,
        ajax: {
            type: GET,
            url: Routing.generate('dispatch_editable_logistic_units_api', {dispatch: dispatchId}, true),
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        domConfig: {
            removeInfo: true,
        },
        ordering: !modifiable,
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

                const $nature = $row.find(`[name=nature]`);
                $nature
                    .off('change.initializePacksTable')
                    .on('change.initializePacksTable', function () {
                        const $defaultQuantityForDispatch = $(this).find('option:selected').data('default-quantity-for-dispatch');
                        const $quantity = $row.find('input[name="quantity"]');
                        const oldQuantity = $quantity.val();
                        if (oldQuantity === null || oldQuantity === '') {
                            $quantity.val($defaultQuantityForDispatch);
                        }
                    });
            })

            $rows
                .off(`focusout.keyboardNavigation`)
                .on(`focusout.keyboardNavigation`, function(event) {
                    const $row = $(this);
                    const $target = $(event.target);
                    const $relatedTarget = $(event.relatedTarget);

                    const wasPackSelect = $target.closest(`td`).find(`select[name="pack"]`).exists();
                    const isStillInSelect = ($relatedTarget.is('input') || $relatedTarget.is('select'))
                        && ($target.closest('label').find('select[name=width]').exists()
                            || $target.closest('label').find('select[name=length]').exists()
                            || $target.closest('label').find('select[name=height]').exists());

                    if ((event.relatedTarget && $.contains(this, event.relatedTarget))
                        || $relatedTarget.is(`button.delete-pack-row`)
                        || wasPackSelect
                        || isStillInSelect) {
                        return;
                    }

                    savePackLine(dispatchId, $row);
                });
            if(modifiable) {
                scrollToBottom();
            }
            if (!$table.data('initialized')) {
                $table.data('initialized', true);
                // Resize table to avoid bug related to WIIS-8276,
                // timeout is necessary because drawCallback doesnt seem to be called when everything is fully loaded,
                // because we have some custom rendering actions which may take more time than native datatable rendering
                setTimeout(() => {
                    afterDatatableLoadAction($table, table)
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
        columns,
    });

    if(modifiable) {
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
                .append(
                    $('<span/>', {
                        title: code,
                        text: code,
                    }),
                    $('<input/>', {
                        type: 'hidden',
                        name: 'pack',
                        class: 'data',
                        value: code,
                    }),
                );
            $row.find(`.d-none`).removeClass(`d-none`);
            $row.find(`[name=weight]`).val(value.weight);
            $row.find(`[name=volume]`).val(value.volume);
            $row.find(`[name=comment]`).val(value.stripped_comment);
            $row.find(`.lastMvtDate`).text(value.lastMvtDate);
            $row.find(`.lastLocation`).text(value.lastLocation);
            $row.find(`.operator`).text(value.operator);
            $row.find(`.status`).text(Translation.of('Demande', 'Acheminements', 'Général', 'À traiter', false));
            $row.find(`.height`).text(value.height);
            $row.find(`.width`).text(value.width);
            $row.find(`.length`).text(value.length);

            if (defaultQuantity !== undefined) {
                $quantity.val(defaultQuantity);
            }

            if(value.nature_id && value.nature_label) {
                $nature.val(value.nature_id);
            }

            table.columns.adjust().draw();

            if (!($quantity.val() && $nature.val())) {
                $quantity.trigger('focus');
            }
            else if(Form.process($row, {hideErrors: true}) instanceof FormData) {
                // trigger savePackLine;
                $row.trigger('focusout.keyboardNavigation');
                addPackRow(table, $(`.add-pack-row`))
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

    $table.on(`click`, `.delete-pack-row`, function() {
        confirmRemovePack(table, $(this));
    });

    $table
        .off(`select2:open.initializePacksTable`)
        .on(`select2:open.initializePacksTable`, function() {
            $table.DataTable().columns.adjust();
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

function afterDatatableLoadAction($table, table)  {
    $table.DataTable().columns.adjust().draw();

    const rowsCount = table.rows().count();

    // Get the index of the last row with the 'add-pack-row' class
    const lastRowIndex = table.row($(`.add-pack-row`)).index() - 1;

    const $lastRow = table.row(lastRowIndex).node();

    if ($lastRow) {
        const lastRowFilled = $( $lastRow ).find('select[name="pack"]').val() !== null;

        // Add a new pack row if the last row is filled and rowsCount is valid
        if (rowsCount > 0 && lastRowFilled) {
            addPackRow(table, $(`.add-pack-row`));
        }
    }
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

function getStatusHistory(dispatch) {
    return $.get(Routing.generate(`dispatch_status_history_api`, {dispatch}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
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
            AJAX.route('GET', 'dispatch_reference_in_logistic_units_api', params)
                .json()
                .then(data => {
                    $logisticUnitsContainer.html(data.html);
                    $logisticUnitsContainer.find('.articles-container table')
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
        $modalAddReference.find("[name=height]").val(description["height"]);
        $modalAddReference.find("[name=width]").val(description["width"]);
        $modalAddReference.find("[name=length]").val(description["length"]);
        $modalAddReference.find("[name=volume]").val(description["volume"]).prop("disabled", true);

        registerVolumeChanges();
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

function registerVolumeChanges() {
    let $inputs = $(`input[name=length], input[name=width], input[name=height]`);
    $inputs.off('input');
    $inputs.on(`input`, () => {
        computeDescriptionFormValues({
            $length: $(`input[name=length]`),
            $width: $(`input[name=width]`),
            $height: $(`input[name=height]`),
            $volume: $(`input[name=volume]`),
            $size: $(`input[name=size]`),
        });
    });
}

function selectUlChanged($select){
    const $modal = $('#modalAddLogisticUnit');
    const ulData = $select.select2('data')[0];

    if (ulData) {
        const defaultNatureId = ulData.nature_id || $modal.find('[name=defaultNatureId]').val();
        const defaultNatureLabel = ulData.nature_label || $modal.find('[name=defaultNatureLabel]').val();
        const defaultQuantityNatureForDispatch = ulData.nature_default_quantity_for_dispatch || $modal.find('[name=defaultQuantityNatureForDispatch]').val();

        const $ulLastMvtDate = $modal.find('.ul-last-movement-date');
        const $ulLastLocation = $modal.find('.ul-last-location');
        const $ulOperator = $modal.find('.ul-operator');
        const $ulNature = $modal.find('select[name=nature]');
        const $ulQuantity = $modal.find('[name=quantity]');
        const oldQuantity = $ulQuantity.val();

        if (oldQuantity === null || oldQuantity === '') {
            $ulQuantity.val(defaultQuantityNatureForDispatch);
        }

        $ulLastMvtDate.text(ulData.lastMvtDate || '-');
        $ulLastLocation.text(ulData.lastLocation || '-');
        $ulOperator.text(ulData.operator || '-');
        if (defaultNatureId && defaultNatureLabel) {
            $ulNature
                .append(new Option(defaultNatureLabel, defaultNatureId, true, true))
                .trigger('change');
        }
        $modal
            .find('[name=packID]')
            .val(ulData.exists ? ulData.id : null);
    }
}

function registerVolumeCompute() {
    $(document).arrive(`[name=height], [name=width], [name=length]`, function() {
        $(this).on(`select2:close change`, function() {
            const $line = $(this).closest(`tr`);
            const $fields = $line.find(`[name=height], [name=width], [name=length]`);
            const $volume = $line.find(`[name=volume]`);
            if(Array.from($fields).some((element) => isNaN($(element).val()) || $(element).val() === null || $(element).val() === '')) {
                $volume.val(null);
            } else {
                const value = Array.from($fields).reduce((acc, element) => acc * Number($(element).val()), 1);
                $volume.val(value.toFixed(6));
            }
        });
    });
}
function confirmRemovePack(table, $pack) {
    const commonConfig = {
        message: Translation.of('Demande', 'Acheminements', 'Détails acheminement - Liste des unités logistiques', 'Voulez-vous vraiment supprimer la ligne ?'),
        title: Translation.of('Demande', 'Acheminements', 'Détails acheminement - Liste des unités logistiques', 'Supprimer la ligne'),
        validateButton: {
            color: 'danger',
            label: Translation.of('Général', null, 'Modale', 'Supprimer'),
        },
        table,
    };

    if ($pack.data('id')) {
        Modal.confirm({
            ...commonConfig,
            ajax: {
                method: 'DELETE',
                route: 'dispatch_delete_pack',
                params: {
                    pack: $pack.data('id'),
                },
            },
        });
    } else {
        Modal.confirm({
            ...commonConfig,
            onSuccess: () => {
                const $row = $pack.closest('tr');
                table.row($row).remove().draw();
            },
        });
    }
}

