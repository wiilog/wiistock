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

function loadAndDisplayInfos($select, fromTouchTerminalSettings = null) {
    const $form = $select.closest('.ligneFournisseurArticle');
    const $nomSelect = fromTouchTerminalSettings ? $form.find('[name="FOURNISSEUR_LABEL_REFERENCE_CREATE"]') : $form.find('[name="fournisseurLabel"]');
    if($select.val()) {
        const [selected] = $select.select2('data');
        if (selected) {
            const {id, text} = selected;
            const [nomSelectSelected] = $nomSelect.select2('data');
            const selectNomFournisseur = () => {
                let option = new Option(text, id, true, true);
                $nomSelect.append(option).trigger('change');
            }
            if (nomSelectSelected) {
                const {id: nomSelectId} = nomSelectSelected;
                if (id !== nomSelectId) {
                    selectNomFournisseur();
                }
            }
            else {
                selectNomFournisseur();
            }
        }
    }
    else {
        $nomSelect.val(null).trigger('change');
    }
    let $modal = $select.closest('.modal');

    $select.parent()
        .siblings('.newContent')
        .removeClass('d-none')
        .addClass('d-block');

    $modal.find('span[role="textbox"]').each(function () {
        $(this).parent().css('border-color', '');
    });
}

function loadAndDisplayLabels($select, fromTouchTerminalSettings = null) {
    const $form = $select.closest('.ligneFournisseurArticle');
    const $codeSelect = fromTouchTerminalSettings ? $form.find('[name="FOURNISSEUR_REFERENCE_CREATE"]') : $form.find('[name="fournisseur"]');
    if($select.val()) {
        const [selected] = $select.select2('data');
        if (selected) {
            const {id, code} = selected;
            const [codeSelectSelected] = $codeSelect.select2('data');
            const selectCodeFournisseur = () => {
                let option = new Option(code, id, true, true);
                $codeSelect.append(option).trigger('change');
            }
            if (codeSelectSelected) {
                const {id: codeSelectId} = codeSelectSelected;
                if (id !== codeSelectId) {
                    selectCodeFournisseur();
                }
            }
            else {
                selectCodeFournisseur();
            }
        }
        else {
            $codeSelect.val(null).trigger('change');
        }
    }
}

function toggleRequiredChampsFixes(button, parent = '.modal') {
    let $modal = button.closest(parent);
    clearErrorMsg(button);
    clearInvalidInputs($modal);
    displayRequiredChampsFixesByTypeQuantiteReferenceArticle(button.data('title'), button, parent);
}
