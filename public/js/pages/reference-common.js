function addReferenceToCart($element) {
    const reference = $element.data('reference');
    const path = Routing.generate('add_ref_to_cart', {reference});

    $.post(path, function(response) {
        if (response.success) {
            showBSAlert('Référence ajoutée au panier.', 'success');
        }
    });
}
