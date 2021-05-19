function createPurchaseRequest(){
    $.post(Routing.generate('purchase_request_new'), function(data) {
        showBSAlert(data.msg, data.success ? 'success' : 'danger');
        if (data.redirect) {
            window.location.href = data.redirect;
        }
    });
}
