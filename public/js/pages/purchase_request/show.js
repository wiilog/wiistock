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
            {"data": 'orderNumber', 'title': 'N° commande'}
        ],
    });



    const $modalEdit = $('#modalEditPurchaseRequestLine');
    const $submitEdit = $modalEdit.find('.submit-button');
    const urlEditLine = Routing.generate('purchase_request_line_edit', true);
    InitModal($modalEdit, $submitEdit, urlEditLine, {tables: [tablePurchaseRequestLines]});

    let $modalAdd = $("#modalAddPurchaseRequestLine");
    let $submitAdd = $modalAdd.find('.submit-button');
    let urlAddLine = Routing.generate('purchase_request_add_reference', {purchaseRequest: id});
    InitModal($modalAdd, $submitAdd, urlAddLine, {tables: [tablePurchaseRequestLines]});

    let modalDeleteRequest = $("#modalDeleteRequest");
    let submitDeleteRequest = $("#submitDeleteRequest");
    let urlDeleteRequest = Routing.generate('purchase_request_delete', true)
    InitModal(modalDeleteRequest, submitDeleteRequest, urlDeleteRequest);

    let $modalEditPurchaseRequest = $('#modalEditPurchaseRequest');
    let $submitEditPurchaseRequest = $('#submitEditPurchaseRequest');
    let urlEditPurchaseRequest = Routing.generate('purchase_request_edit', true);
    InitModal($modalEditPurchaseRequest, $submitEditPurchaseRequest, urlEditPurchaseRequest);

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

            const $label = $modal.find('[name="label"]');
            const $buyer = $modal.find('[name="buyer"]');
            const $stockQuantity = $modal.find('[name="stockQuantity"]');
            $label.val(data.label);
            $buyer.val(data.buyer);
            $stockQuantity.val(data.stockQuantity);

            const $requestedQuantity = $modal.find('[name="requestedQuantity"]');
            $requestedQuantity.val(null);

            const $container = $modal.find(".line-form-following-container");
            $container.removeClass('d-none');

            const $submitButton = $modal.find(".submit-button");
            $submitButton.removeAttr('disabled');
        })
        .catch(() => {
            clearLineAddModal();
        });
}

function clearLineAddModal(clearReferenceInput = false){
    const $modal = $('#modalAddPurchaseRequestLine');

    if (clearReferenceInput) {
        const $reference = $modal.find('[name="reference"]');
        $reference
            .val(null)
            .trigger('change');
    }

    const $container = $modal.find(".line-form-following-container");
    $container.addClass('d-none');

    const $label = $modal.find('[name="label"]');
    const $buyer = $modal.find('[name="buyer"]');
    const $stockQuantity = $modal.find('[name="stockQuantity"]');
    $label.val(null);
    $buyer.val(null);
    $stockQuantity.val(null);

    const $submitButton = $modal.find(".submit-button");
    $submitButton.prop('disabled', true);
}

function callbackEditLineLoading($modal) {
    initDateTimePicker('#modalEditPurchaseRequestLine .datepicker[name="orderDate"]', 'DD/MM/YYYY HH:mm', false);
    initDateTimePicker('#modalEditPurchaseRequestLine .datepicker[name="expectedDate"]', 'DD/MM/YYYY HH:mm', false);
    let $orderDateInput = $('#modalEditPurchaseRequestLine').find('[name="orderDate"]');
    let orderDate = $orderDateInput.attr('data-date');

    let $expectedDateInput = $('#modalEditPurchaseRequestLine').find('[name="expectedDate"]');
    let expectedDate = $expectedDateInput.attr('data-date');

    if(orderDate){
        console.log("TEST", orderDate);
        $orderDateInput.val(moment(orderDate, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));
    }
    if(expectedDate){
        console.log("TEST", expectedDate);
        $expectedDateInput.val(moment(expectedDate, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));
    }

    Select2Old.provider($modal.find('.ajax-autocomplete-fournisseur'));
}
