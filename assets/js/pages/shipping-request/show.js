import Form from "@app/form";
import AJAX, {GET, POST} from "@app/ajax";
import Modal from "@app/modal";
import {initModalFormShippingRequest} from "@app/pages/shipping-request/form";
import {ERROR} from "@app/flash";

global.validateShippingRequest = validateShippingRequest;
global.openScheduledShippingRequestModal = openScheduledShippingRequestModal;
global.generateDeliverySlip = generateDeliverySlip;
global.treatShippingRequest = treatShippingRequest;
global.deleteExpectedLine = deleteExpectedLine;
global.deleteShippingRequest = deleteShippingRequest;

let expectedLines = null;
let packingData = [];
let packCount = null;
let scheduledShippingRequestFormData = null;
let shippingId = null;

$(function() {
    shippingId = $('[name=shippingId]').val();

    const $modalEdit = $('#modalEditShippingRequest');
    initModalFormShippingRequest($modalEdit, 'shipping_request_edit', () => {
        $modalEdit.modal('hide');
        window.location.reload();
    });

    initScheduledShippingRequestForm();
    initPackingPack($('#modalPacking'))
    getShippingRequestStatusHistory();
    updateDetails();

    $(document).arrive('.schedule-details', function () {
        initDetailsScheduled($(this));
    });

    $(document).arrive('#expectedLinesEditableTable', function () {
        initShippingRequestExpectedLine($(this));
    });

    $(document).arrive('#expectedLinesTable', function () {
        initDetailsToTreat($(this));
    });
});

function refreshTransportHeader(){
    AJAX.route(GET, 'shipping_request_header_config', {
        id: shippingId
    })
        .json()
        .then(({detailsTransportConfig}) => {
            $('.transport-header').html(detailsTransportConfig);
        });
}

function validateShippingRequest($button) {
    if($('#expectedLinesEditableTable').find('.is-invalid').length > 0){
        Flash.add(ERROR, 'Tous les champs obligatoires ne sont pas remplis');
    } else {
        wrapLoadingOnActionButton($button, () => (
            AJAX.route(GET, `shipping_request_validation`, {shippingRequest: shippingId})
                .json()
                .then((res) => {
                    updatePage();
                })
        ));
    }
}

function deleteShippingRequest($event){
    const shipping_request_id = $event.data('id');
    wrapLoadingOnActionButton($(".row.wii-column.w-100"), function () {
        AJAX.route(`DELETE`, `delete_shipping_request`, {id: shipping_request_id})
            .json()
            .then((res) => {
                if (res.success) {
                    window.location.href = Routing.generate('shipping_request_index');
                }
            });
    });
}


function initScheduledShippingRequestForm() {
    let $modalScheduledShippingRequest = $('#modalScheduledShippingRequest');
    Form.create($modalScheduledShippingRequest).onSubmit((data, form) => {
        $modalScheduledShippingRequest.modal('hide');
        openPackingModal(data, expectedLines, 1);
    });
}

function deleteExpectedLine($button, table) {
    const lineId = $button.data('id');
    if (lineId) {
        Modal.confirm({
            ajax: {
                method: AJAX.DELETE,
                route: 'shipping_request_expected_line_delete',
                params: { line: lineId },
            },
            message: 'Voulez-vous réellement supprimer cette ligne de référence ?',
            title: 'Supprimer le client',
            validateButton: {
                color: 'danger',
                label: 'Supprimer'
            },
            table,
        })
    }
    else {
        const row = table.row($button.closest(`tr`));
        row.remove();
        table.draw();
    }
}

function initPackingPack($modal) {
    $modal.find('button.nextStep').on('click', function () {
        packingNextStep($modal);
    })

    $modal.find('button.previous').on('click', function () {
        packingPreviousStep($modal);
    })

    $modal
        .on('hidden.bs.modal', function () {
            $modal.find('.modal-body').empty();
        })
        .on('click', 'button[type=submit]', function () {
            if (packingNextStep($modal, true)) {
                const packing = packingData.map(function (packingPack) {
                    const lineData = packingPack.lines.map(function (lineFormData) {
                        let linesData = {}
                        if (Boolean(Number(lineFormData.get('picked')))) {
                            lineFormData.forEach(function (value, key) {
                                linesData[key] = value
                            })
                        }
                        return linesData
                    }).filter((lineData) => Object.keys(lineData).length)
                    const packData = {}
                    packingPack.pack.forEach(function (value, key) {
                        packData[key] = value
                    })

                    return {
                        lines: lineData,
                        ...packData
                    }
                })
                let scheduleData = {}
                scheduledShippingRequestFormData.forEach(function (value, key) {
                    scheduleData[key] = value
                });
                wrapLoadingOnActionButton($(this), function () {
                    AJAX
                        .route(POST, 'shipping_request_submit_packing', {id: shippingId,})
                        .json(
                            {
                                packing,
                                scheduleData,
                            }
                        )
                        .then((res) => {
                            updatePage();
                            if (res.success) {
                                $modal.modal('hide');

                            }
                        });
                })
            }
        });
}

function openPackingModal(dataShippingRequestForm, expectedLines, step = 1) {
    scheduledShippingRequestFormData = dataShippingRequestForm;
    const $modal = $('#modalPacking');
    $modal.modal('show');

    packCount = Number(dataShippingRequestForm.get('packCount'));
    const actionTemplate = $modal.find('#actionTemplate');
    const quantityInputTemplate = $modal.find('#quantityInputTemplate');
    const isLastStep = step === packCount;
    const lines = expectedLines.map(function (expectedLine) {
        return {
            'selected': fillActionTemplate(actionTemplate, expectedLine.referenceArticleId, expectedLine.lineId, isLastStep, isLastStep),
            'reference' : expectedLine.reference,
            'label': expectedLine.label,
            'quantity': fillQuantityInputTemplate(quantityInputTemplate, expectedLine.quantity, isLastStep),
            'price': expectedLine.price,
            'weight': expectedLine.weight,
            'totalPrice': expectedLine.totalPrice,
        }
    });

    $modal.find('[name=packCount]').html(packCount);
    packingAddStep($modal, lines, step);

    $modal.find('.modal-body').on('change', 'input[name=quantity]', function () {
        const $row = $(this).closest('tr');
        const lineId = $row.find('[name=lineId]').val();
        $row.find('span.total-price').html($(this).val() * expectedLines.find(line => Number(line.lineId) === Number(lineId)).price);
    })
}

function fillActionTemplate(template, referenceArticleId, lineId, picked = false, disabled = false) {
    const $template = $(template).clone()
    $template.find('[name="referenceArticleId"]').val(referenceArticleId);
    $template.find('[name="lineId"]').val(lineId);
    $template.find('[name="picked"]').attr('disabled', disabled).attr('checked', picked);
    return $template.html()
}

function fillQuantityInputTemplate(template, quantity, isLastStep) {

    const $template = $(template).clone();
    const $quantityInput = $template.find('[name=quantity]')

    if (quantity === 1 || isLastStep) {
        $quantityInput.parent().append(quantity);
        $quantityInput.attr('type', 'hidden');
    } else {
        $quantityInput.attr('max', quantity);
    }

    $quantityInput.attr('value', quantity.toString());
    $template.find('[name=remainingQuantity]').val(quantity);
    return $template.html()
}

async function packingAddStep($modal, data, step) {
    const packTemplate = $modal.find('#packTemplate').clone().html();
    const $curentStep = $modal.find('.modal-body').append(packTemplate).find('.packing-step:last')
    $curentStep.attr('data-step', step);
    $modal.find('[name=step]').html(step);
    $curentStep.find('[name=modalNumber]').html(step);

    let tablePackingConfig = {
        processing: true,
        serverSide: false,
        ordering: true,
        paging: false,
        order: [['label', 'desc']],
        searching: false,
        domConfig: {
            removeInfo: true,
        },
        data: data,
        columns: [
            {name: 'selected', data: 'selected', title: '', orderable: false},
            {name: 'reference', data: 'reference', title: 'Référence', orderable: true},
            {name: 'label', data: 'label', title: 'Libellé', orderable: true},
            {name: 'quantity', data: 'quantity', title: 'Quantité', orderable: false},
            {name: 'price', data: 'price', title: 'Prix unitaire (€)', orderable: true},
            {name: 'weight', data: 'weight', title: 'Poids net(Kg)', orderable: true},
            {name: 'totalPrice', data: 'totalPrice', title: 'Montant total', orderable: true},
        ],
    };
    const $table = $curentStep.find('.articles-container table').attr('id', 'packingTable' + step)
    await initDataTable($table, tablePackingConfig);
    $modal.find('[name=quantity]').trigger('change');

    managePackingModalButtons($modal, step)
}

function managePackingModalButtons($modal, step) {
    $modal.find('button.btn.nextStep').attr('hidden', step === packCount);
    $modal.find('button[type=submit]').attr('hidden', step !== packCount);
    $modal.find('button.previous').attr('hidden', step === 1);
}

function packingNextStep($modal, finalStep = false) {
    const $lastStep = $modal.find('.modal-body .packing-step:last');
    let step = $lastStep.data('step');
    const packForm = Form.process($lastStep.find('.main-ul-data'));
    const $stepTable = $lastStep.find('.articles-container table');
    const $checkValidation = Form
        .create($stepTable)
        .addProcessor((data, errors, $form) => {
            const $picked = $form.find('[name=picked]:checked');
            if (!$picked.length) {
                errors.push({
                    global: true,
                    message: 'Veuillez sélectionner au moins une ligne',
                });
                data.append('atLeastOneLine', false);
            } else {
                data.append('atLeastOneLine', true);
            }
        })
        .process();

    const linesForm = []
    $stepTable.find('tbody tr').each(function (index, tr) {
        linesForm
            .push(Form
                .create($(tr))
                .addProcessor((data, errors, $form) => {
                    if ($(this).find('[name=picked]').is(':checked') && !$(this).find('[name=quantity]').val()) {
                        errors.push({
                            elements: [$(this).find('[name=quantity]')],
                            global: false,
                        });
                    }
                })
                .process());
    });

    // check if there is an error in the step
    if (!packForm || linesForm.includes(false) || !$checkValidation?.get('atLeastOneLine')) {
        return false;
    }

    packingData[step - 1] = {
        pack: packForm,
        lines: linesForm,
    }

    const actionTemplate = $modal.find('#actionTemplate')
    const quantityInputTemplate = $modal.find('#quantityInputTemplate')
    const nextStepData = packingData[step - 1]['lines'].map(function (lineFormData) {
        const expectedLine = expectedLines.find(expectedLine => Number(expectedLine.lineId) === Number(lineFormData.get('lineId')));
        const quantity = getNextStepQuantity(lineFormData.get('picked'), lineFormData.get('quantity'), lineFormData.get('remainingQuantity'));
        const nextIsLastStep = (step + 1) === packCount;

        if (quantity !== 0) {
            return {
                'selected': fillActionTemplate(actionTemplate, lineFormData.get('referenceArticleId'), lineFormData.get('lineId'), nextIsLastStep, nextIsLastStep),
                'reference': expectedLine.reference,
                'label': expectedLine.label,
                'quantity': fillQuantityInputTemplate(quantityInputTemplate, getNextStepQuantity(lineFormData.get('picked'), lineFormData.get('quantity'), lineFormData.get('remainingQuantity')), nextIsLastStep),
                'price': expectedLine.price,
                'weight': expectedLine.weight,
                'totalPrice': expectedLine.totalPrice,
            }
        }
    }).filter(line => line);

    if (!finalStep) {
        $lastStep.hide();
        packingAddStep($modal, nextStepData, step + 1)
    }
    return true;
}

function getNextStepQuantity(picked, quantity, remainingQuantity) {
    return Boolean(Number(picked)) ? Number(remainingQuantity) - Number(quantity) : Number(remainingQuantity);
}

function packingPreviousStep($modal) {
    const $actualStep = $modal.find('.modal-body .packing-step:last');
    $actualStep.remove();

    const $lastStep = $modal.find('.modal-body .packing-step:last');
    $lastStep.show();
    const step = $lastStep.data('step');
    $modal.find('[name=step]').html(step);
    managePackingModalButtons($modal, step)
}

function openScheduledShippingRequestModal($button){
    AJAX.route(GET, `get_format_expected_lines`, {id:$button.data('id')})
        .json()
        .then((res) => {
            if (res.success) {
                $('#modalScheduledShippingRequest').modal('show');
                expectedLines = res.expectedLines;
            }
        });
}

function initShippingRequestExpectedLine($table) {

    const table = initDataTable($table, {
        serverSide: false,
        ajax: {
            type: AJAX.GET,
            url: Routing.generate('api_shipping_request_expected_lines', {request: shippingId}, true),
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        domConfig: {
            removeInfo: true,
        },
        drawConfig: {
            needsColumnHide: true,
        },
        columns: [
            {data: 'actions', orderable: false, alwaysVisible: true, class: 'noVis', width: '10px'},
            {data: 'reference', title: 'Référence', required: true, width: '180px'},
            {data: 'information', orderable: false, alwaysVisible: true, class: 'noVis', width: '10px'},
            {data: 'editAction', orderable: false, alwaysVisible: true, class: 'noVis', width: '10px'},
            {data: 'label', title: 'Libellé'},
            {data: 'quantity', title: 'Quantité', required: true,},
            {data: 'price', title: 'Prix unitaire (€)', required: true,},
            {data: 'weight', title: 'Poids net (kg)', required: true,},
            {data: 'total', title: 'Montant Total',},
        ],
        ordering: false,
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

            $rows.each(function () {
                const $row = $(this);
                const data = Form.process($row, {
                    ignoreErrors: true,
                });

                $row.data(`data`, JSON.stringify(data instanceof FormData ? data.asObject() : data));
            });

            $rows.off(`focusout.keyboardNavigation`).on(`focusout.keyboardNavigation`, function (event) {
                const $row = $(this);
                const $target = $(event.target);
                const $relatedTarget = $(event.relatedTarget);


                const wasLineSelect = $target.closest(`td`).find(`[name="reference"]`).exists();
                if ((event.relatedTarget && $.contains(this, event.relatedTarget))
                    || $relatedTarget.is(`button.delete-row`)
                    || wasLineSelect) {
                    return;
                }

                saveExpectedLine(shippingId, $row);
            });

            scrollToBottom();
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
                    .addClass('add-row');
                $row.find('span.add-row').removeClass('add-row');
                $tdOther.addClass('d-none');
            }
        },
    });

    scrollToBottom();
    $table.on(`keydown`, `[name="quantity"]`, function (event) {
        if (event.key === `.` || event.key === `,` || event.key === `-` || event.key === `+` || event.key === `e`) {
            event.preventDefault();
            event.stopPropagation();
        }
    });

    $table.on(`click`, `.add-row`, function () {
        addExpectedLineRow(table, $(this));
    });

    $table.on(`change`, `[name=referenceArticle]`, function (event) {
        const $reference = $(event.target);
        const [reference] = $reference.select2('data');

        const $row = $reference.closest('tr');
        const $labelWrapper = $row.find('.label-wrapper');
        $labelWrapper.text(reference?.label || '');

        if (reference) {
            $reference.select2('destroy');
            $reference.replaceWith(
                $('<span/>', {
                    html: [
                        $('<span/>', {text: reference.text}),
                        $('<input/>', {
                            type: 'hidden',
                            class: 'data',
                            value: reference.id,
                            name: 'referenceArticle',
                        }),
                    ]
                })
            );
            $row.find('.editAction')
                .removeClass('d-none')
                .attr('href', Routing.generate('reference_article_edit_page', {reference: reference.id}));

            if (reference.dangerous) {
                $row
                    .find('.dangerous')
                    .removeClass('d-none');
            }
        }

        table.columns.adjust();
    });

    $table.on(`change`, `[name=price], [name=quantity]`, function (event) {
        const $target = $(event.target);
        const $row = $target.closest('tr');
        const quantity = $row.find('[name=quantity]').val();
        const price = $row.find('[name=price]').val();
        const $total = $row.find('.total-wrapper');

        if (quantity && price) {
            $total.text(quantity * price);
        }
    });

    $table.on(`click`, `.delete-row`, function (event) {
        deleteExpectedLine($(this), table);
    });

    $table.on(`keydown`, function(event) {
        const tabulationKeyCode = 9;

        const $target = $(event.target);
        // check if input is the last of the row
        const lastInputOfRow = $target.is(
            $target
                .closest('tr')
                .find('.data')
                .last()
        );

        if (event.keyCode === tabulationKeyCode
            && lastInputOfRow) {
            event.preventDefault();
            event.stopPropagation();

            const $nextRow = $target.closest(`tr`).next();
            const $addRowButton = $nextRow.find(`.add-row`);
            if($addRowButton.exists()) {
                addExpectedLineRow(table, $addRowButton);
            }
        }
    });

    $(window).on(`beforeunload`, () =>  {
        const $focus = $(`tr :focus`);
        if($focus.exists()) {
            if(saveExpectedLine(shippingId, $focus.closest(`tr`))) {
                return true;
            }
        }
    });

    return table;
}

function saveExpectedLine(requestId, $row) {
    let data = Form.process($row);
    data = data instanceof FormData ? data.asObject() : data;

    if (data) {
        if (!jQuery.deepEquals(data, JSON.parse($row.data(`data`)))) {
            AJAX
                .route(AJAX.POST, `shipping_request_submit_changes_expected_lines`, {shippingRequest: requestId})
                .json(data)
                .then((response) => {
                    if (response.success) {
                        if (response.lineId) {
                            $row.find(`.delete-row`)
                                .attr('data-id', response.lineId)
                                .data('id', response.lineId);
                            $row.find('input[name="lineId"]').val(response.lineId);
                        }
                        $row
                            .data(`data`, JSON.stringify(data))
                            .attr(`data-data`, JSON.stringify(data));
                        refreshTransportHeader();
                    }
                });
        }
        return true;
    } else {
        $row.find('.is-invalid').first().trigger('focus');
        return false;
    }
}

function addExpectedLineRow(table, $button) {
    const $table = $button.closest('table');
    if (Form.process($table)) {
        const row = table.row($button.closest(`tr`));
        const data = row.data();

        row.remove();
        table.row.add(JSON.parse($(`input[name="editableExpectedLineForm"]`).val()));
        table.row.add(data);
        table.draw();

        scrollToBottom();

        // find added row
        const $lastRow = $table.find('tbody tr:last-child');
        const $addedRow = $lastRow.prev();

        // wait for the row to be added
        setTimeout(() => {
            $addedRow.find('select.needed[required]').first().select2('open');
        }, 100);
    }
}

function getShippingRequestStatusHistory() {
    return AJAX.route(GET, `shipping_request_status_history_api`, {shippingRequest: shippingId})
        .json()
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.html(template);
        });
}

function updateDetails() {
    AJAX
        .route(GET, `shipping_request_get_details`, {id: shippingId})
        .json()
        .then(({html}) => {
            $('.details-container').html(html);
        });
}

function initDetailsScheduled($container) {
    $container.find('.logistic-unit-wrapper .articles-container .table').each(function () {
        let $table = $(this);

        const columns = [
            {name: 'actions', data: 'actions', title: '', orderable: false},
            {name: 'reference', data: 'reference', title: 'Référence', orderable: true},
            {name: 'label', data: 'label', title: 'Libellé', orderable: true},
            {name: 'quantity', data: 'quantity', title: 'Quantité', orderable: true},
            {name: 'price', data: 'price', title: 'Prix unitaire (€)', orderable: true},
            {name: 'weight', data: 'weight', title: 'Poids net (kg)', orderable: true},
            {name: 'totalPrice', data: 'totalPrice', title: 'Montant total', orderable: true},
        ];

        initDataTable($table, {
            serverSide: false,
            ordering: true,
            paging: false,
            searching: false,
            processing: true,
            order: [['reference', "desc"]],
            columns,
            rowConfig: {},
            domConfig: {
                removeInfo: true,
                removeLength: true,
                needsPaginationRemoval: true,
            },
            drawConfig: {},
        });
    });
}

function initDetailsToTreat($table) {
    const columns = [
        {name: 'actions', data: 'actions', title: '', orderable: false},
        {name: 'reference', data: 'reference', title: 'Référence', orderable: true},
        {name: 'label', data: 'label', title: 'Libellé', orderable: true},
        {name: 'quantity', data: 'quantity', title: 'Quantité', orderable: true},
        {name: 'price', data: 'price', title: 'Prix unitaire (€)', orderable: true},
        {name: 'weight', data: 'weight', title: 'Poids net (kg)', orderable: true},
        {name: 'total', data: 'total', title: 'Montant total', orderable: true},
    ];

    initDataTable($table, {
        serverSide: false,
        ordering: true,
        paging: false,
        searching: false,
        processing: true,
        order: [['reference', "desc"]],
        columns,
        rowConfig: {},
        domConfig: {
            removeInfo: true,
        },
        drawConfig: {},
    });
}


function treatShippingRequest($button) {
    AJAX.route(GET, `check_expected_lines_data`, {id: $button.data('id')})
        .json()
        .then((res) => {
            if (res.success) {
                wrapLoadingOnActionButton($button, () => (
                    AJAX.route(POST, `treat_shipping_request`, {shippingRequest: shippingId})
                        .json()
                        .then(() => {
                            updatePage();
                        })
                ));
            }
        });

}

function updatePage() {
    getShippingRequestStatusHistory();
    updateDetails();
    refreshTransportHeader();
}

function generateDeliverySlip(shippingRequestId) {
    AJAX.route(AJAX.POST, 'post_delivery_slip', {shippingRequest: shippingRequestId})
        .json()
        .then(({attachmentId}) => {
            AJAX.route(AJAX.GET, 'print_delivery_slip', {
                shippingRequest: shippingRequestId,
                attachment: attachmentId,
            })
            .file({
                success: "Votre bordereau de livraison a bien été imprimé.",
                error: "Erreur lors de l'impression du bordereau de livraison."
            })
        });
}

