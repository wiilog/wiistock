function buildErrorMsgReferenceArticle(missingInputs, wrongNumberInputs, doublonRef) {
    let msg = '';

    if(doublonRef ){
        msg+= "Il n'est pas possible de rentrer plusieurs références d'article fournisseur identiques. Veuillez les différencier. <br>";
    }

    // cas où il manque des champs obligatoires
    if (missingInputs.length > 0) {
        if (missingInputs.length == 1) {
            msg += 'Veuillez renseigner le champ ' + missingInputs[0] + ".<br>";
        } else {
            msg += 'Veuillez renseigner les champs : ' + missingInputs.join(', ') + ".<br>";
        }
    }
    // cas où les champs number ne respectent pas les valeurs imposées (min et max)
    if (wrongNumberInputs.length > 0) {
        wrongNumberInputs.forEach(function (elem) {
            let label = elem.closest('.form-group').find('label').text();
            // on enlève l'éventuelle * du nom du label
            label = label.replace(/\*/, '');
            missingInputs.push(label);

            msg += 'La valeur du champ ' + label;

            let min = elem.attr('min');
            let max = elem.attr('max');

            if (typeof (min) !== 'undefined' && typeof (max) !== 'undefined') {
                msg += ' doit être comprise entre ' + min + ' et ' + max + ".<br>";
            } else if (typeof (min) == 'undefined') {
                msg += ' doit être inférieure à ' + max + ".<br>";
            } else if (typeof (max) == 'undefined') {
                msg += ' doit être supérieure à ' + min + ".<br>";
            }
        })
    }

    return msg;
}

function getDataFromModalReferenceArticle(modal) {
    // On récupère toutes les données qui nous intéressent
    // dans les inputs...
    let Data = {};
    let inputs = modal.find(".data");
    // Trouver les champs correspondants aux infos fournisseurs...
    let fournisseursWithRefAndLabel = [];
    let fournisseurReferences = modal.find('input[name="referenceFournisseur"]');
    let labelFournisseur = modal.find('input[name="labelFournisseur"]');
    let refsF = [];
    let missingInputs = [];
    let wrongNumberInputs = [];
    let doublonRef = false;
    modal.find('select[name="fournisseur"]').each(function (index) {
        if ($(this).val()) {
            if (fournisseurReferences.eq(index).val()) {
                fournisseursWithRefAndLabel.push($(this).val() + ';' + fournisseurReferences.eq(index).val() + ';' + labelFournisseur.eq(index).val());
                if (refsF.includes(fournisseurReferences.eq(index).val())) {
                    doublonRef = true;
                    fournisseurReferences.eq(index).addClass('is-invalid');
                } else {
                    refsF.push(fournisseurReferences.eq(index).val());
                }
            }
        }
    });
    Data['frl'] = fournisseursWithRefAndLabel;
    inputs.each(function () {
        const $input = $(this);
        let val = $input.val();
        let name = $input.attr("name");
        if (!Data[name] || parseInt(Data[name], 10) === 0) {
            Data[name] = val;
        }
        let label = $input.closest('.form-group').find('label').first().text();
        // validation données obligatoires
        if ($input.hasClass('needed') && (val === undefined || val === '' || val === null)) {
            // on enlève l'éventuelle * du nom du label
            label = label.replace(/\*/, '');
            missingInputs.push(label);
            $input.addClass('is-invalid');
            $input.next().find('.select2-selection').addClass('is-invalid');
        }

        // validation valeur des inputs de type number
        // protection pour les cas où il y a des champs cachés
        if ($input.attr('type') === 'number' && $input.hasClass('needed')) {
            let val = parseInt($input.val());
            let min = parseInt($input.attr('min'));
            let max = parseInt($input.attr('max'));
            if (val > max || val < min || isNaN(val)) {
                wrongNumberInputs.push($input);
                $input.addClass('is-invalid');
            }
        }
    });
    // ... et dans les checkboxes
    let checkboxes = modal.find('.checkbox');
    checkboxes.each(function () {
        Data[$(this).attr("name")] = $(this).is(':checked');
    });
    return { Data, missingInputs, wrongNumberInputs, doublonRef };
}

function displayRequiredChampsFixesByTypeQuantiteReferenceArticle(typeQuantite, $button) {
    let $modal = $button.closest('.modal');
    if (typeQuantite === 'article') {
        $modal.find('#quantite').removeClass('needed');
        $modal.find('#emplacement').removeClass('needed');
        $modal.find('.type_quantite').val('article');
    } else {
        $modal.find('#quantite').addClass('needed');
        $modal.find('#emplacement').addClass('needed');
        $modal.find('.type_quantite').val('reference');
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
        const xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                dataReponse = JSON.parse(this.responseText);
                $('#addFournisseur').closest('div').before(dataReponse);
                ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
            }
        };
        let path = Routing.generate('ajax_render_add_fournisseur', true);
        xhttp.open("POST", path, true);
        xhttp.send();
    }
}

function loadAndDisplayInfos($select) {
    let $modal = $select.closest('.modal');

    $select.parent()
        .siblings('.newContent')
        .removeClass('d-none')
        .addClass('d-block');

    $modal.find('span[role="textbox"]').each(function () {
        $(this).parent().css('border-color', '');
    });
}
