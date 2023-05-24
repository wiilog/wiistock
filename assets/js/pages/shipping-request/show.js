import Form from "@app/form";
import AJAX, {GET, POST} from "@app/ajax";
import {initModalFormShippingRequest} from "@app/pages/shipping-request/form";

global.validateShippingRequest = validateShippingRequest;
global.openScheduledShippingRequestModal = openScheduledShippingRequestModal;

let expectedLines = null;
let packingData = [];
let packCount = null;
let scheduledShippingRequestFormData = null;

$(function() {
    const shippingId = $('[name=shippingId]').val();


    const $modalEdit = $('#modalEditShippingRequest');
    initModalFormShippingRequest($modalEdit, 'shipping_request_edit', () => {
        $modalEdit.modal('hide');
        window.location.reload();
    });

    initScheduledShippingRequestForm();
    initPackingPack($('#modalPacking'))
    getShippingRequestStatusHistory(shippingId);
});

function refreshTransportHeader(shippingId) {
    AJAX.route(GET, 'shipping_request_header_config', {
        id: shippingId
    })
        .json()
        .then(({detailsTransportConfig}) => {
            $('.transport-header').empty().append(detailsTransportConfig);
        });
}

function validateShippingRequest(shipping_request_id) {
    AJAX.route(GET, `shipping_request_validation`, {id: shipping_request_id})
        .json()
        .then((res) => {
            if (res.success) {
                location.reload()
            }
        });
}

function initScheduledShippingRequestForm() {
    let $modalScheduledShippingRequest = $('#modalScheduledShippingRequest');
    Form.create($modalScheduledShippingRequest).onSubmit((data, form) => {
        $modalScheduledShippingRequest.modal('hide');
        openPackingModal(data, expectedLines, 1);
    });
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
                    const shippingId = $('[name=shippingId]').val();
                    AJAX
                        .route(POST, 'shipping_request_submit_packing', {id: shippingId,})
                        .json(
                            {
                                packing,
                                scheduleData,
                            }
                        )
                        .then((res) => {
                            if (res.success) {
                                $modal.modal('hide');
                                refreshTransportHeader(shippingId);
                                getShippingRequestStatusHistory(shippingId);
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
        $quantityInput.parents().append(quantity);
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

function openScheduledShippingRequestModal($button) {
    const id = $button.data('id')
    AJAX.route(GET, `check_expected_lines_data`, {id})
        .json()
        .then((res) => {
            if (res.success) {
                $('#modalScheduledShippingRequest').modal('show');
                expectedLines = res.expectedLines;
            }
        });
}

function getShippingRequestStatusHistory(shippingRequest) {
    return AJAX.route(GET, `shipping_request_status_history_api`, {shippingRequest})
        .json()
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.html(template);
        });
}
