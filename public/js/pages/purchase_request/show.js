let tableReference;

$(function() {

    tableArticle = initDataTable('tableArticle', {
        ajax: {
            "url": Routing.generate('purchase_request_article_api', {purchaseRequest: id}, true),
            "type": "POST"
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

    let modal = $("#modalAddReference");
    let submit = $("#submitAddReference");
    let url = Routing.generate('purchase_request_add_reference', {purchaseRequest: id});
    InitModal(modal, submit, url, {tables: [tableReference]});

    let modalDeleteRequest = $("#modalDeleteRequest");
    let submitDeleteRequest = $("#submitDeleteRequest");
    let urlDeleteRequest = Routing.generate('purchase_request_delete', true)
    InitModal(modalDeleteRequest, submitDeleteRequest, urlDeleteRequest);
});

$('.select2').select2();
