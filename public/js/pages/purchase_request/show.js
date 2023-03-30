$(function() {
    const purchaseRequestBuyerId = $('#purchase-request-buyer-id').val();
    Select2Old.articleReference($('#add-article-reference'), {
        buyerFilter: purchaseRequestBuyerId,
    });

    const tablePurchaseRequestLines = initDataTable('tablePurchaseRequestLine', {
        ajax: {
            "url": Routing.generate('purchase_request_lines_api', {purchaseRequest: id}, true),
            "type": "GET"
        },
        order: [['reference', 'desc']],
        rowConfig: {
            needsRowClickAction: true,
        },
        columns: [
            {"data": 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'reference', 'title': 'Référence'},
            {"data": 'label', 'title': 'Libellé'},
            {"data": 'requestedQuantity', 'title': 'Quantité demandée'},
            {"data": 'stockQuantity', 'title': 'Quantité en stock'},
            {"data": 'orderedQuantity', 'title': 'Quantité commandée'},
            {"data": 'orderNumber', 'title': 'N° commande'},
            {"data": 'supplier', 'title': 'Fournisseur'},
            {"data": 'location', 'title': 'Emplacement (Zone)'}
        ],
    });

    const $modalEditLine = $('#modalEditPurchaseRequestLine');
    const $submitEditLine = $modalEditLine.find('.submit-button');
    const urlEditLine = Routing.generate('purchase_request_line_edit', true);
    InitModal($modalEditLine, $submitEditLine, urlEditLine, {tables: [tablePurchaseRequestLines]});

    let $modalAddLine = $("#modalAddPurchaseRequestLine");
    let $submitAddLine = $modalAddLine.find('.submit-button');
    let urlAddLine = Routing.generate('purchase_request_add_line', {purchaseRequest: id});
    InitModal($modalAddLine, $submitAddLine, urlAddLine, {tables: [tablePurchaseRequestLines]});

    let modalDeleteRequest = $("#modalDeleteRequest");
    let submitDeleteRequest = $("#submitDeleteRequest");
    let urlDeleteRequest = Routing.generate('purchase_request_delete', true)
    InitModal(modalDeleteRequest, submitDeleteRequest, urlDeleteRequest);

    let $modalDeleteLine = $("#modalDeletePurchaseRequestLine");
    let $submitDeleteLine = $modalDeleteLine.find('.submit-button');
    let urlDeleteLine = Routing.generate('purchase_request_line_remove_line', true)
    InitModal($modalDeleteLine, $submitDeleteLine, urlDeleteLine, {tables: [tablePurchaseRequestLines]});

    let $modalEditPurchaseRequest = $('#modalEditPurchaseRequest');
    let $submitEditPurchaseRequest = $('#submitEditPurchaseRequest');
    let urlEditPurchaseRequest = Routing.generate('purchase_request_edit', true);
    InitModal($modalEditPurchaseRequest, $submitEditPurchaseRequest, urlEditPurchaseRequest);

    const $modalConsiderPurchaseRequest = $('#modalConsiderPurchaseRequest');
    const $submitConsiderPurchaseRequest = $modalConsiderPurchaseRequest.find('.submit-button');
    const urlConsiderPurchaseRequest = Routing.generate('consider_purchase_request', {id: id}, true);
    InitModal($modalConsiderPurchaseRequest, $submitConsiderPurchaseRequest, urlConsiderPurchaseRequest);

    const $modalTreatPurchaseRequest = $('#modalTreatPurchaseRequest');
    const $submitTreatPurchaseRequest = $modalTreatPurchaseRequest.find('.submit-button');
    const urlTreatPurchaseRequest = Routing.generate('treat_purchase_request', {id: id}, true);
    InitModal($modalTreatPurchaseRequest, $submitTreatPurchaseRequest, urlTreatPurchaseRequest);

    let $modalValidatePurchaseRequest = $('#modalValidatePurchaseRequest');
    let $submitValidatePurchaseRequest = $('#submitValidatePurchaseRequest');
    let urlValidatePurchaseRequest = Routing.generate('purchase_request_validate', {id: id}, true);
    InitModal($modalValidatePurchaseRequest, $submitValidatePurchaseRequest, urlValidatePurchaseRequest, {
        success: () => {
            window.location.reload();
        }
    });

    Select2Old.init($modalEditPurchaseRequest.find('select[name=status]'));
});

function onReferenceChange($select) {
    let reference = $select.val();
    if(!reference) {
        clearLineAddModal();
        return;
    }

    let route = Routing.generate('get_reference_data', {reference});

    $.get(route)
        .then((data) => {
            const $modal = $select.closest(".modal");
            const $location = $modal.find('[name="location"]');
            const $quantity = $modal.find('[name="stockQuantity"]');
            $location.attr('disabled', false);
            $location.empty();
            $modal.find('[name="label"]').val(data.label);
            $modal.find('[name="buyer"]').val(data.buyer);
            $quantity.val(data.stockQuantity);
            $quantity.data('init', data.stockQuantity);

            const $requestedQuantity = $modal.find('[name="requestedQuantity"]');
            $requestedQuantity.val(null);

            const $container = $modal.find(".line-form-following-container");
            $container.removeClass('d-none');

            const $submitButton = $modal.find(".submit-button");
            $submitButton.removeAttr('disabled');


            const quantityType = data.quantityType;

            if (quantityType === 'reference') {
                $location.attr('disabled', true);
            } else {
                const locationsOption = JSON.parse(data.locations);
                console.log(locationsOption);

                const $option = $(new Option('', '',false, false));
                $location.append($option);
                locationsOption.forEach((option) => {
                    const $option = $(new Option(option.location.label, option.location.id,false, false));
                    $option
                        .data('quantity',  option.quantity)
                        .attr('data-quantity', option.quantity);
                    $location.append($option);
                });

                $location.on('change', function() {
                    const quantity = $(this).find('option:selected').data('quantity');
                    if (quantity !== undefined) {
                        $quantity.val(data.quantity);
                    } else {
                        $quantity.val($quantity.data('init'));
                    }
                })
            }
        })
        .catch(() => {
            clearLineAddModal();
        });
}

function clearLineAddModal(clearReferenceInput = false) {
    const $modal = $('#modalAddPurchaseRequestLine');

    if (clearReferenceInput) {
        $modal
            .find('[name="reference"]')
            .val(null)
            .trigger('change');
    }

    const $container = $modal.find(".line-form-following-container");
    $container.addClass('d-none');

    $modal.find('[name="label"]').val(null);
    $modal.find('[name="buyer"]').val(null);
    $modal.find('[name="stockQuantity"]').val(null);

    const $submitButton = $modal.find(".submit-button");
    $submitButton.prop('disabled', true);
}

function callbackEditLineLoading($modal) {
    initDateTimePicker('#modalEditPurchaseRequestLine .datepicker[name="orderDate"]', 'DD/MM/YYYY HH:mm');
    initDateTimePicker('#modalEditPurchaseRequestLine .datepicker[name="expectedDate"]', 'DD/MM/YYYY');
    let $orderDateInput = $('#modalEditPurchaseRequestLine').find('[name="orderDate"]');
    let orderDate = $orderDateInput.attr('data-date');

    let $expectedDateInput = $('#modalEditPurchaseRequestLine').find('[name="expectedDate"]');
    let expectedDate = $expectedDateInput.attr('data-date');

    if(orderDate){
        $orderDateInput.val(moment(orderDate, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));
    }
    if(expectedDate){
        $expectedDateInput.val(moment(expectedDate, 'YYYY-MM-DD').format('DD/MM/YYYY'));
    }

    Select2Old.provider($modal.find('.ajax-autocomplete-fournisseur'));
}

function openEvolutionModal($modal) {
    clearModal($modal);
    $modal.modal('show');
}

function deleteRowLine(button, $submit) {
    let id = button.data('id');
    $submit.attr('value', id);
}
