function displayRequiredChampsFixesByTypeQuantiteReferenceArticle(typeQuantite, $button, parent = '.modal') {
    const $modal = $button.closest(parent);
    const $location = $modal.find('[name="emplacement"]');
    const $quantity = $modal.find('[name="quantite"]');

    if (typeQuantite === 'reference') {
        $quantity.addClass('needed');
        $location.addClass('needed');
        $modal.find('.type_quantite').val('reference');
    } else {
        $quantity.removeClass('needed');
        $location.removeClass('needed');
        $modal.find('.type_quantite').val('article');
    }
}

function addArticleFournisseurReferenceArticle($plusButton) {
    const $inputLastLine = $plusButton
        ? $plusButton.parent()
            .siblings('.ligneFournisseurArticle')
            .last()
            .find('.form-control')
            .toArray()
        : [];

    if ($inputLastLine.length === 0 || $inputLastLine.every((formControl) => formControl.value)) {
        const lastArticleFournisseurForm = $plusButton
            .parent()
            .siblings('.ligneFournisseurArticle')
            .last();

        $.ajax({
            url: Routing.generate('ajax_render_add_fournisseur', { currentIndex: lastArticleFournisseurForm.data('multiple-object-index') }, true),
            type: "get",
            contentType: false,
            processData: false,
            cache: false,
            dataType: "json"
        }).then((response) => {
            $(response).insertBefore($plusButton.parent());
            Select2Old.provider($('.ajax-autocomplete-fournisseur'));
            Select2Old.provider($('.ajax-autocomplete-fournisseurLabel'), '', 'demande_label_by_fournisseur');
        });
    }
}

function toggleRequiredChampsFixes(button, parent = '.modal') {
    let $modal = button.closest(parent);
    clearErrorMsg(button);
    clearInvalidInputs($modal);
    displayRequiredChampsFixesByTypeQuantiteReferenceArticle(button.data('title'), button, parent);
}
