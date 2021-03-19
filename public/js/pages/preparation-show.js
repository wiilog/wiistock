$('.select2').select2();

let tableArticleSplitting;
let prepaHasBegun = false;
let $preparationId = $('#prepa-id');
let $modalSubmitPreparation = $('#modal-select-location');
let pathArticle = Routing.generate('preparation_article_api', {'preparation': $preparationId.val()});

$(function () {
    const $locationSelect = $modalSubmitPreparation.find('select[name="location"]');
    Select2Old.location($locationSelect);

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
    order: [['Référence', "asc"]]
};

let tableArticle = initDataTable('tableArticle_id', tableArticleConfig);

function startPicking($button, managementType) {
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
                },
            };
            if (managementType) {
                tableSplittingArticlesConfig.order = [
                    4, "asc"
                ];
            }
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
    const success = validatePreparationArticlesSplitting();
    if (success) {
        const $inputs = $('#tableSplittingArticles').find('.input');
        const chosenArticles = $inputs
            .toArray()
            .reduce((acc, input) => {
                const $input = $(input);
                const val = Number($input.val());
                return {
                    ...acc,
                    [$input.data('id')]: val || 0
                };
            }, {})

        let path = Routing.generate('submit_splitting', true);
        let params = {
            'articles': chosenArticles,
            'quantite': submit.data('qtt'),
            'demande': submit.data('demande'),
            'refArticle': submit.data('ref'),
            'preparation': submit.data('prep')
        };

        $.post(path, JSON.stringify(params), function (response) {
            if (response.success == true) {
                $('#modalSplitting').find('.close').click();
                tableArticle.ajax.reload();
            }
            else if (response.msg) {
                $('#modalSplitting').find('.error-msg').html(response.msg);
                showBSAlert(response.msg, 'danger');
            }
        });
    }
}

function addToScissionAll($checkbox) {
    let $input = $checkbox.closest('td').find('.input');

    if (!$checkbox.is(':checked')) {
        $input.prop('disabled', false);
        $input.val('');
    } else {
        $input.val($checkbox.data('quantite'));
        $input.prop('disabled', true);
    }

    validatePreparationArticlesSplitting();
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

function validatePreparationArticlesSplitting() {
    const $inputs = $('#tableSplittingArticles').find('.input');
    const quantityToTake = Number($('#scissionTitle').data('quantity-to-take'));
    const $modalErrorContainer = $('#modalSplitting').find('.error-msg');
    const $remainingQuantity = $('#remainingQuantity');
    const $remainingQuantityParent = $remainingQuantity.parent();

    let remainingQuantity = quantityToTake;

    $modalErrorContainer.html('');
    $remainingQuantityParent.removeClass('red');
    $remainingQuantityParent.removeClass('green');

    let success = true;
    let message;

    $inputs.each(function() {
        const $input = $(this);
        const validInput = validateSplittingArticle($input);
        success = success && validInput;
    });

    if (!success) {
        message = 'Les quantités sélectionnées sont invalides.';
    }
    else {
        const totalQuantityTaken = $inputs
            .toArray()
            .reduce((acc, {value}) => (acc + Number(value || 0)), 0);

        remainingQuantity = quantityToTake - totalQuantityTaken;

        if (totalQuantityTaken <= 0) {
            success = false;
            message = 'Vous devez sélectionner au moins un article.';
        }
        else if (totalQuantityTaken > quantityToTake) {
            success = false;
            const overQuantity = -1 * remainingQuantity;
            const s = overQuantity > 1 ? 's' : '';
            message = `Vous avez prélevé une quantité supérieure à celle demandée (${overQuantity} article${s} en trop).`;
        }

        if (remainingQuantity < 0) {
            $remainingQuantityParent.addClass('red');
        } else if (totalQuantityTaken) {
            $remainingQuantityParent.addClass('green');
        }
    }

    $modalErrorContainer.html(message);
    $remainingQuantity.html(remainingQuantity > 0 ? remainingQuantity : 0);

    return success;
}

function validateSplittingArticle($input) {
    const $rowError = $input.parent().find('.row-error-msg');
    let validInput = true;
    const val = $input.val();
    const max = Number($input.attr('max'));
    if (val) {
        const valNumber = Number($input.val());
        if (isNaN(valNumber)) {
            $input.val('');
        }
        else if (valNumber < 0) {
            validInput = false;
            $rowError.html('min : 1');
            $input.addClass('is-invalid');
        }
        else if (valNumber > max) {
            validInput = false;
            $rowError.html(`max : ${max}`);
            $input.addClass('is-invalid');
        }
    }
    else {
        $input.val('');
    }

    if (validInput) {
        $input.removeClass('is-invalid');
        $rowError.html('');
    }

    return validInput;
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

