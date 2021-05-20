function addReferenceToCart($element) {
    const reference = $element.data(`reference`);
    const path = Routing.generate(`cart_add_reference`, {reference});

    $.post(path, function(response) {
        if (response.success) {
            $(`.cart-total`).text(response.count)[response.count ? `removeClass` : `addClass`](`d-none`);
            showBSAlert(response.msg, `success`);
        } else {
            showBSAlert(response.msg, `warning`);
        }
    });
}
