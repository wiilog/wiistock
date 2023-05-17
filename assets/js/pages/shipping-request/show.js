import Form from "@app/form";
import AJAX, {GET, POST} from "@app/ajax";

global.validateShippingRequest = validateShippingRequest;
global.openScheduledShippingRequestModal = openScheduledShippingRequestModal;

let expectedLines = null;
let packingData = [];

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
        openPackingPack(data, expectedLines);
    });
}

function openPackingPack(dataShippingRequestForm, expectedLines, step = 1){
    const $modal = $('#modalPacking');
    $modal.modal('show');

    $modal.find('button.nextStep').on('click', function(){
        packingNexStep($modal);
    })

    const actionTemplate = $modal.find('#actionTemplate')
    const quantityInputTemplate = $modal.find('#quantityInputTemplate')
    const lines = expectedLines.map(function (expectedLine)  {
        return {
            'selected': fillActionTemplate(actionTemplate, expectedLine.referenceArticleId, expectedLine.lineId),
            'label': expectedLine.label,
            'quantity': expectedLine.quantity === 1 ? 1 : fillQuantityInputTemplate(quantityInputTemplate, expectedLine.quantity, expectedLine.quantity),
            'price': expectedLine.price,
            'weight': expectedLine.weight,
            'totalPrice': expectedLine.totalPrice,
        }
    });

    packingAddStep($modal, lines, dataShippingRequestForm.get('packCount'), step);
}

function fillActionTemplate(template, referenceArticleId, lineId){
    const $template =  $(template)
    $template.find('[name="referenceArticleId"]').val(referenceArticleId);
    $template.find('[name="lineId"]').val(lineId);
    return $template.html()
}

function fillQuantityInputTemplate(template, quantity, remainingQuantity){
    const $template =  $(template)
    $template.find('[name=quantity]').val(quantity);
    $template.find('[name=quantity]').attr('max', quantity);
    $template.find('[name=remainingQuantity]').html(remainingQuantity);
    return $template.html()
}

function packingAddStep($modal, data, packCount, step){
    const packTemplate = $modal.find('#packTemplate').html();

    const $curentStep = $modal.find('.modal-body').append(packTemplate)
    $curentStep.data('step', step);
    $modal.find('[name=step]').html(step);
    $modal.find('[name=packCount]').html(packCount);

    // TODO REMLINE LABEL PACK

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
    initDataTable($modal.find('.articles-container table'), tablePackingConfig);
}

function packingNexStep($modal){
    const $lastStep = $modal.find('.modal-body .packing-step:last');
    const step = $lastStep.data('step');
    const packForm =  Form.process($lastStep.find('.main-ul-data'));

    const linesForm = []
    $lastStep.find('.articles-container table tbody tr').each(function (index, tr) {
        linesForm
            .push(Form.create($(tr))
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

    if (!packForm || linesForm.find(element => false)) {
        return;
    }
    packingData[step] = {
        pack: packForm,
        lines: linesForm,
    }
    packingData[step]['pack'].forEach(function (value, key) {
      console.log(value, key, 'pack')
    })
    packingData[step]['lines'].forEach(function (value, key) {
      console.log(value, key, 'line')
    })

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
