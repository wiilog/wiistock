$('.select2').select2();

let tableArticleSplitting;
let prepaHasBegun = false;
let $preparationId = $('#prepa-id');
let $modalSubmitPreparation = $('#modal-select-location');
let pathArticle = Routing.generate('preparation_article_api', {'preparation': $preparationId.val()});

$(function () {
    const $locationSelect = $modalSubmitPreparation.find('select[name="location"]');
    Select2.location($locationSelect);

    $(document).on('hidden.bs.modal','#modalSplitting', function () {
        $('.action-on-click-single').data('clicked', false);
    });
});

let tableArticleConfig = {
    ajax: pathArticle,
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité en stock'},
        {"data": 'Quantité à prélever', 'title': 'Quantité à prélever'},
        {"data": 'Quantité prélevée', 'name': 'quantitePrelevee', 'title': 'Quantité prélevée'},
    ],
    rowConfig: {
        needsRowClickAction: true,
        needsColor: true,
        color: 'success',
        dataToCheck: 'active'
    },
    order: [[1, "asc"]]
};

let tableArticle = initDataTable('tableArticle_id', tableArticleConfig);

function startPicking($button) {
    if (!$button.data('clicked')) {
        $('.action-on-click-single').data('clicked', true);
        let ligneArticleId = $button.attr('value');

        let path = Routing.generate('start_splitting', true);
        $.post(path, JSON.stringify(ligneArticleId), function (html) {
            $('#splittingContent').html(html);
            let tableSplittingArticlesConfig = {
                'lengthMenu': [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'tous']],
                domConfig: {
                    needsPaginationRemoval: true
                }
            };
            tableArticleSplitting = initDataTable('tableSplittingArticles', tableSplittingArticlesConfig);
            $('#modalSplitting').modal('show');
        });
    }
}

let urlEditLigneArticle = Routing.generate('prepa_edit_ligne_article', true);
let modalEditLigneArticle = $("#modalEditLigneArticle");
let submitEditLigneArticle = $("#submitEditLigneArticle");
InitModal(modalEditLigneArticle, submitEditLigneArticle, urlEditLigneArticle, {tables: [tableArticle]});

function submitSplitting(submit) {
    let $inputs = $('#tableSplittingArticles').find('.input');

    let articlesChosen = {};
    let quantityToZero = false;
    let maxExceeded = false;
    for (const input of $inputs) {
        const $input = $(input);
        const inputValue = $input.val() !== '' ? Number($input.val()) : '';
        const inputMax = $input.attr('max') !== '' ? Number($input.attr('max')) : 0;
        const inputValueInit = $input.data('value-init') !== '' ? Number($input.data('value-init')) : 0;

        if (inputValue !== '' && inputValue > 0) {
            if (inputValue <= inputMax) {
                let id = $input.data('id');
                articlesChosen[id] = inputValue;
                $input.removeClass('is-invalid');
            } else {
                maxExceeded = true;
                $input.addClass('is-invalid');
            }
        } else if (inputValueInit > 0) {
            quantityToZero = true;
            $input.addClass('is-invalid');
            break;
        }
    }

    if (maxExceeded) {
        $('#modalSplitting').find('.error-msg').html("Vous avez trop sélectionné pour un article.");
    } else if ($('#remainingQuantity').val() < 0) {
        $('#modalSplitting').find('.error-msg').html("Vous avez prélevé une quantité supérieure à celle demandée.");
    } else if (quantityToZero) {
        $('#modalSplitting').find('.error-msg').html("Vous ne pouvez pas renseigner de quantité inférieure à 1 pour cet article.");
    } else if (Object.keys(articlesChosen).length > 0) {
        let path = Routing.generate('submit_splitting', true);
        let params = {
            'articles': articlesChosen,
            'quantite': submit.data('qtt'),
            'demande': submit.data('demande'),
            'refArticle': submit.data('ref'),
            'preparation': submit.data('prep')
        };
        $.post(path, JSON.stringify(params), function (resp) {
            if (resp == true) {
                $('#modalSplitting').find('.close').click();
                tableArticle.ajax.reload();
            }
        });
    } else {
        $('#modalSplitting').find('.error-msg').html("Vous devez sélectionner une quantité pour enregistrer.");
    }
}

function limitInput($input) {
    // vérification quantité disponible référence
    let value = Number($input.val());
    let thisMax = Number($input.attr('max'));

    if (value > thisMax) {
        $input.parent().find('.row-error-msg').html('max : ' + thisMax);
    } else {
        $input.parent().find('.row-error-msg').html('');
    }
}

function addToScissionAll($checkbox) {
    let $input = $checkbox.closest('td').find('.input');

    if (!$checkbox.is(':checked')) {
        $input.prop('disabled', false);
        $input.val('');
        limitInput($input);
    } else {
        if (parseFloat($('#quantiteRestante').html()) > 0) {
            $input.val($checkbox.data('quantite'));
            limitInput($input);
            $input.prop('disabled', true);
        } else {
            $checkbox.prop('checked', false);
        }
    }

    updateRemainingQuantity();
}

function beginPrepa() {
    if (!prepaHasBegun) {
        let prepaId = $preparationId.val();
        let path = Routing.generate('prepa_begin');

        $.post(path, prepaId, () => {
            prepaHasBegun = true;
        });
    }
}

function updateRemainingQuantity() {
    let $inputs = $('#tableSplittingArticles').find('.input');
    const $remainingQuantitySelector = $('#quantiteRestante');

    let totalQuantityTaken = 0;
    $inputs.each(function () {
        if ($(this).val() != '') {
            totalQuantityTaken += parseFloat($(this).val()) - $(this).data('value-init');
        } else {
            totalQuantityTaken -= $(this).data('value-init');
        }
    });

    let quantityToTake = $('#scissionTitle').data('quantity-to-take');
    let remainingQuantity = quantityToTake - totalQuantityTaken;
    $remainingQuantitySelector.html(String(Math.max(0, remainingQuantity)));
    $remainingQuantitySelector.val(remainingQuantity);

    if (remainingQuantity < 0) {
        let s = remainingQuantity < -1 ? 's' : '';
        $('#modalSplitting').find('.error-msg').html('(' + -remainingQuantity + ' article' + s + ' en trop)');
        $remainingQuantitySelector.parent().addClass('red');
        $remainingQuantitySelector.parent().removeClass('green');
    } else if (remainingQuantity > 0) {
        $('#modalSplitting').find('.error-msg').html('');
        $remainingQuantitySelector.parent().addClass('red');
        $remainingQuantitySelector.parent().removeClass('green')
    } else {
        $('#modalSplitting').find('.error-msg').html('');
        $remainingQuantitySelector.parent().removeClass('red');
        $remainingQuantitySelector.parent().addClass('green');
    }
}

function finishPrepa($button) {
    let allRowsEmpty = true;

    let rows = tableArticle
        .column('quantitePrelevee:name')
        .data();

    rows.each((elem) => {
        if (elem > 0) allRowsEmpty = false;
    });

    if (allRowsEmpty) {
        showBSAlert('Veuillez sélectionner au moins une ligne.', 'danger');
    } else if (!$button.hasClass('loading')) {
        clearValidatePreparationModal();
        $modalSubmitPreparation.modal('show');
        const $locationSelect = $modalSubmitPreparation.find('select[name="location"]');
        const $submitButtonPreparation = $modalSubmitPreparation.find('button[type="submit"]');

        $submitButtonPreparation.off('click');
        $submitButtonPreparation.on('click', function () {
            const value = $locationSelect.val();
            if (value) {
                $modalSubmitPreparation.modal('hide');
                wrapLoadingOnActionButton(
                    $button,
                    () => (
                        $.post({
                            url: Routing.generate('preparation_finish', {'idPrepa': $preparationId.val()}),
                            data: {
                                emplacement: value
                            }
                        })
                            .then(({success, redirect, message}) => {
                                if (success) {
                                    window.location.href = redirect;
                                } else {
                                    showBSAlert(message, 'danger');
                                }

                                return success;
                            })
                    ),
                    false);
            } else {
                showBSAlert('Veuillez sélectionner un emplacement.', 'danger');
            }
        });
    } else {
        showBSAlert('La préparation est en cours de traitement.', 'success');
    }
}

function printPrepaBarCodes() {
    const lengthPrintButton = $('.print-button').length;

    if (lengthPrintButton > 0) {
        window.location.href = Routing.generate(
            'preparation_bar_codes_print',
            {
                preparation: $preparationId.val()
            },
            true
        );
    } else {
        showBSAlert("Il n'y a aucun article à imprimer.", 'danger');
    }
}

function clearValidatePreparationModal() {
    const $locationSelect = $modalSubmitPreparation.find('select[name="location"]')
    $locationSelect.html('');
    $locationSelect.val('');
}

