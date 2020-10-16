function displayRequiredChampsFixesByTypeQuantiteReferenceArticle(typeQuantite, $button) {
    let $modal = $button.closest('.modal');
    if (typeQuantite === 'article') {
        $modal.find('#quantite').removeClass('needed');
        $modal.find('#emplacement').removeClass('needed');
        $modal.find('.type_quantite').val('article');
        $modal.find('.stockManagement').removeClass('d-none');
    } else {
        $modal.find('#quantite').addClass('needed');
        $modal.find('#emplacement').addClass('needed');
        $modal.find('.type_quantite').val('reference');
        $modal.find('.stockManagement').addClass('d-none');
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
            Select2.provider($('.ajax-autocompleteFournisseur'));
            Select2.provider($('.ajax-autocompleteFournisseurLabel'), '', 'demande_label_by_fournisseur');
        });
    }
}

function loadAndDisplayInfos($select) {
    const $form = $select.parents('.ligneFournisseurArticle');
    const $nomSelect = $form.find('.ajax-autocompleteFournisseurLabel');
    if($select.val()) {
        const [selected] = $select.select2('data');
        if (selected) {
            const {id, name} = selected;
            const [nomSelectSelected] = $nomSelect.select2('data');
            const selectNomFournisseur = () => {
                let option = new Option(name, id, true, true);
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
function loadAndDisplayLabels($select) {
    const $form = $select.parents('.ligneFournisseurArticle');
    const $codeSelect = $form.find('.ajax-autocompleteFournisseur');
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

function toggleRequiredChampsFixes(button) {
    let $modal = button.closest('.modal');
    clearErrorMsg(button);
    clearInvalidInputs($modal);
    displayRequiredChampsFixesByTypeQuantiteReferenceArticle(button.data('title'), button);
}
