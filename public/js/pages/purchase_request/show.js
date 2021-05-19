$(function() {
    const purchaseRequestBuyerId = $('#purchase-request-buyer-id').val();
    Select2Old.articleReference($('#add-article-reference'), {
        buyerFilter: purchaseRequestBuyerId,
    });

    const tablePurchaseRequestLine = initDataTable('tablePurchaseRequestLine', {
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
            {"data": 'reservedQuantity', 'title': 'Quantité commandée'},
            {"data": 'orderNumber', 'title': 'N° commande'}
        ],
    });

    let $modal = $("#modalAddPurchaseRequestLine");
    let $submit = $modal.find('.submit-button');
    let url = Routing.generate('purchase_request_add_reference', {purchaseRequest: id});
    InitModal($modal, $submit, url, {tables: [tablePurchaseRequestLine]});

    let modalDeleteRequest = $("#modalDeleteRequest");
    let submitDeleteRequest = $("#submitDeleteRequest");
    let urlDeleteRequest = Routing.generate('purchase_request_delete', true)
    InitModal(modalDeleteRequest, submitDeleteRequest, urlDeleteRequest);

    let $modalEditPurchaseRequest = $('#modalEditPurchaseRequest');
    let $submitEditPurchaseRequest = $('#submitEditPurchaseRequest');
    let urlEditPurchaseRequest = Routing.generate('purchase_request_edit', true);
    InitModal($modalEditPurchaseRequest, $submitEditPurchaseRequest, urlEditPurchaseRequest);

    let $modalValidatePurchaseRequest = $('#modalValidatePurchaseRequest');
    let $submitValidatePurchaseRequest = $('#submitValidatePurchaseRequest');
    let urlValidatePurchaseRequest = Routing.generate('purchase_request_validate', true);
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
        })
       /* , function(response) {
        if (response.success) {
            $("#add-article-code-selector").html(response.html || "");
            $('.error-msg').html('');
        } else {
            $('.error-msg').html(response.msg);
        }*/
    //});
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

function validatePurchaseRequest(PurchaseRequestId, $button) {
    let params = JSON.stringify({id: PurchaseRequestId});

    wrapLoadingOnActionButton($button, () => (
        $.post({
            url: Routing.generate('purchase_request_validate'),
            data: params
        })
            .then(function (resp) {
                if (resp === true) {
                    return getCompareStock($button);
                } else {
                    $('#cannotValidate').click();
                    return false;
                }
            })
    ));
}
