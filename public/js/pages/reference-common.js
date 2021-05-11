function addReferenceToCart($element) {
    const reference = $element.data(`reference`);
    const path = Routing.generate(`add_ref_to_cart`, {reference});

    $.post(path, function(response) {
        if (response.success) {
            $(`.cart-total`).text(response.count);
            showBSAlert(`Référence ajoutée au panier.`, `success`);
        }
    });
}
