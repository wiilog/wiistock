$(function(){
    Select2Old.init($('select[name=status]'));
});

function createPurchaseRequest(){
    $.post(Routing.generate('purchase_request_new')).then(function(data) {
        if(data.success){
            showBSAlert(data.msg, 'success');
        }
    })
}
