$(function(){
    Select2Old.init($('select[name=status]'));
    let $modalEditPurchaseRequest = $('#modalEditPurchaseRequest');
    let $submitEditPurchaseRequest = $('#submitEditPurchaseRequest');
    let urlEditPurchaseRequest = Routing.generate('purchase_request_edit', true);
    InitModal($modalEditPurchaseRequest, $submitEditPurchaseRequest, urlEditPurchaseRequest);
});


