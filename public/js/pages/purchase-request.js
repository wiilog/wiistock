$(function(){

           });

function createPurchaseRequest(){
    $.post(Routing.generate('purchase_request_new'), function(data) {
        showBSAlert(data.msg, data.success ? 'success' : 'danger');
    });
}
