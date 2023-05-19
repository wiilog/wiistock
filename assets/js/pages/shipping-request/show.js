import Form from "@app/form";
import AJAX, {GET, POST} from "@app/ajax";

global.validateShippingRequest = validateShippingRequest;
global.openScheduledShippingRequestModal = openScheduledShippingRequestModal;

let expectedLines = null;
let packingData = [];
let packCount = null;

$(function() {
    const shippingId = $('[name=shippingId]').val();
    initScheduledShippingRequestForm();
});

function refreshTransportHeader(shippingId){
    AJAX.route(POST, 'get_transport_header_config', {
        id: shippingId
    })
        .json()
        .then(({detailsTransportConfig}) => {
            $('.transport-header').empty().append(detailsTransportConfig);
        });
}
function validateShippingRequest(shipping_request_id){
    AJAX.route(GET, `shipping_request_validation`, {id:shipping_request_id})
        .json()
        .then((res) => {
            if (res.success) {
                location.reload()
            }
        });
}

function initScheduledShippingRequestForm(){
    let $modalScheduledShippingRequest = $('#modalScheduledShippingRequest');
    Form.create($modalScheduledShippingRequest).onSubmit((data, form) => {
        $modalScheduledShippingRequest.modal('hide');
        openPackingPack(data, expectedLines, 1);
    });
}

function openPackingPack(dataShippingRequestForm, expectedLines, step = 1){
    const $modal = $('#modalPacking');
    $modal.modal('show');

    $modal.find('button.nextStep').on('click', function(){
        packingNextStep($modal);
    })

    $modal.find('button.previous').on('click', function(){
        packingPreviousStep($modal);
    })

    const actionTemplate = $modal.find('#actionTemplate')
    const quantityInputTemplate = $modal.find('#quantityInputTemplate')
    const lines = expectedLines.map(function (expectedLine)  {
        return {
            'selected': fillActionTemplate(actionTemplate, expectedLine.referenceArticleId, expectedLine.lineId),
            'label': expectedLine.label,
            'quantity': fillQuantityInputTemplate(quantityInputTemplate, expectedLine.quantity),
            'price': expectedLine.price,
            'weight': expectedLine.weight,
            'totalPrice': expectedLine.totalPrice,
        }
    });

    packCount = Number(dataShippingRequestForm.get('packCount'));

    $modal.find('[name=packCount]').html(packCount);
    packingAddStep($modal, lines, step);
}

function fillActionTemplate(template, referenceArticleId, lineId){
    const $template =  $(template).clone()
    $template.find('[name="referenceArticleId"]').val(referenceArticleId);
    $template.find('[name="lineId"]').val(lineId);
    return $template.html()
}

function fillQuantityInputTemplate(template, quantity){
    const $template =  $(template).clone();
    const $quantityInput = $template.find('[name=quantity]')

    if (quantity === 1) {
        $quantityInput.parents().append(quantity);
        $quantityInput.attr('type', 'hidden');
    } else {

        $quantityInput.attr('max', quantity);
    }

    $quantityInput.attr('value',quantity.toString());
    $template.find('[name=remainingQuantity]').val(quantity);
    return $template.html()
}
function packingAddStep($modal, data, step){
    const packTemplate = $modal.find('#packTemplate').clone().html();
    const $curentStep = $modal.find('.modal-body').append(packTemplate).find('.packing-step:last')
    $curentStep.attr('data-step', step);
    $modal.find('[name=step]').html(step);
    $modal.find('[name=modalNumber]').html(step);

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
            {name: 'selected',data: 'selected', title: '', orderable: false},
            {name: 'label',data: 'label', title: 'Libellé', orderable: true},
            {name: 'quantity',data: 'quantity', title: 'Quantité', orderable: false},
            {name: 'price',data: 'price', title: 'Prix unitaire (€)', orderable: true},
            {name: 'weight',data: 'weight', title: 'Poids net(Kg)', orderable: true},
            {name: 'totalPrice',data: 'totalPrice', title: 'Montant total', orderable: true},
        ],
    };
    const $table = $curentStep.find('.articles-container table').attr('id', 'packingTable' + step)
    initDataTable($table, tablePackingConfig);

    $modal.find('button.btn.nextStep').attr('hidden', step === packCount);
    $modal.find('button[type=submit]').attr('hidden', step !== packCount);
    $modal.find('button.previous').attr('hidden', step === 1);
}

function packingNextStep($modal){
    const $lastStep = $modal.find('.modal-body .packing-step:last');
    let step = $lastStep.data('step');
    const packForm =  Form.process($lastStep.find('.main-ul-data'));
    const $stepTable = $lastStep.find('.articles-container table');
    const $checkValidation =  Form
        .create($stepTable)
        .addProcessor((data, errors, $form) => {
            const $checked = $form.find('[name=checked]:checked');
            if (!$checked.length) {
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
                if ($(this).find('[name=checked]').is(':checked') && !$(this).find('[name=quantity]').val()) {
                    errors.push({
                        elements: [$(this).find('[name=quantity]')],
                        global: false,
                    });
                }
            })
            .process());
    });

    // check if there is an error in the step
    if (!packForm || linesForm.includes(false) || !$checkValidation.get('atLeastOneLine')) {
        return;
    }

    packingData[step] = {
        pack: packForm,
        lines: linesForm,
    }

    const actionTemplate = $modal.find('#actionTemplate')
    const quantityInputTemplate = $modal.find('#quantityInputTemplate')
    const nextStepData = packingData[step]['lines'].map(function (lineFormData) {
        const expectedLine = expectedLines.find(expectedLine => Number(expectedLine.lineId) === Number(lineFormData.get('lineId')));
        const quantity = getNextStepQuantity(lineFormData.get('checked'), lineFormData.get('quantity'), lineFormData.get('remainingQuantity'));

        if (quantity !== 0) {
            return {
                'selected': fillActionTemplate(actionTemplate, lineFormData.get('referenceArticleId'), lineFormData.get('lineId')),
                'label': expectedLine.label,
                'quantity': fillQuantityInputTemplate(quantityInputTemplate, getNextStepQuantity(lineFormData.get('checked'), lineFormData.get('quantity'), lineFormData.get('remainingQuantity'))),
                'price': expectedLine.price,
                'weight': expectedLine.weight,
                'totalPrice': expectedLine.totalPrice,
            }
        }
    }).filter(line => line);

    $lastStep.hide();
    packingAddStep($modal, nextStepData, step + 1 )
}

function getNextStepQuantity(checked, quantity, remainingQuantity){
    return Boolean(Number(checked)) ? Number(remainingQuantity) - Number(quantity) : Number(quantity);
}

function packingPreviousStep($modal){
    const $actualStep = $modal.find('.modal-body .packing-step:last');
    $actualStep.remove();

    const $lastStep = $modal.find('.modal-body .packing-step:last');
    $lastStep.show();
    $modal.find('[name=step]').html($lastStep.data('step'));
}

function openScheduledShippingRequestModal($button){
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
