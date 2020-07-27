$('.select2').select2();

let prepaHasBegun = false;
let tableArticleSplitting;
let $modalSubmitPreparation = $('#modal-select-location');
let $preparationId = $('#prepa-id');

$(function () {
    const $locationSelect = $modalSubmitPreparation.find('select[name="location"]')

    initDateTimePicker();
    initSelect2($('#statut'), 'Statuts');

    ajaxAutoDemandesInit($('.ajax-autocomplete-demande'));
    ajaxAutoCompleteEmplacementInit($locationSelect);
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');

    let $filterDemand = $('.filters-container .filter-demand');
    $filterDemand.attr('name', 'demande');
    $filterDemand.attr('id', 'demande');
    let filterDemandId = $('#filterDemandId').val();
    let filterDemandValue = $('#filterDemandValue').val();
    if (filterDemandId && filterDemandValue) {
        let option = new Option(filterDemandValue, filterDemandId, true, true);
        $filterDemand.append(option).trigger('change');
    } else {
        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_PREPA);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }
});

let path = Routing.generate('preparation_api');
let tableConfig = {
    serverSide: true,
    processing: true,
    order: [[3, 'desc']],
    ajax: {
        url: path,
        'data': {
            'filterDemand': $('#filterDemandId').val()
        },
        "type": "POST"
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns: [
        {"data": 'Actions', 'title': '', 'name': 'Actions', className: 'noVis'},
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: 0
        }
    ],
};
let table = initDataTable('table_id', tableConfig);

let pathArticle = Routing.generate('preparation_article_api', {'preparation': $preparationId.val()});

let tableArticleConfig = {
    ajax: pathArticle,
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis'},
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
    order: [[1, "asc"]],
    columnDefs: [
        {'orderable': false, 'targets': [0]}
    ]
};

let tableArticle = initDataTable('tableArticle_id', tableArticleConfig);

function startPicking($button) {
    let ligneArticleId = $button.attr('value');

    let path = Routing.generate('start_splitting', true);
    $.post(path, JSON.stringify(ligneArticleId), function (html) {
        $('#splittingContent').html(html);
        let tableSplittingArticlesConfig = {
            'lengthMenu': [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'tous']],
            'columnDefs': [
                {'orderable': false, 'targets': [3]}
            ],
            domConfig: {
                needsPaginationRemoval: true
            }
        };
        tableArticleSplitting = initDataTable('tableSplittingArticles', tableSplittingArticlesConfig);
        $('#modalSplitting').modal('show');
    });
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

function updateRemainingQuantity() {
    let $inputs = $('#tableSplittingArticles').find('.input');

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
    $('#quantiteRestante').html(String(Math.max(0, remainingQuantity)));
    $('#remainingQuantity').val(remainingQuantity);

    if (remainingQuantity < 0) {
        let s = remainingQuantity < -1 ? 's' : '';
        $('#modalSplitting').find('.error-msg').html('(' + -remainingQuantity + ' article' + s + ' en trop)');
        $('#quantiteRestante').parent().addClass('red');
        $('#quantiteRestante').parent().removeClass('green');
    } else if (remainingQuantity > 0) {
        $('#modalSplitting').find('.error-msg').html('');
        $('#quantiteRestante').parent().addClass('red');
        $('#quantiteRestante').parent().removeClass('green')
    } else {
        $('#modalSplitting').find('.error-msg').html('');
        $('#quantiteRestante').parent().removeClass('red');
        $('#quantiteRestante').parent().addClass('green');
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

function clearValidatePreparationModal() {
    const $locationSelect = $modalSubmitPreparation.find('select[name="location"]')
    $locationSelect.html('');
    $locationSelect.val('');
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

function finishPrepa($button) {
    let allRowsEmpty = true;

    let rows = tableArticle
        .column('quantitePrelevee:name')
        .data();

    rows.each((elem) => {
        if (elem > 0) allRowsEmpty = false;
    });

    if (allRowsEmpty) {
        alertErrorMsg('Veuillez sélectionner au moins une ligne.', true);
    } else {
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
                            }
                            else {
                                alertErrorMsg(message);
                            }

                            return success;
                        })
                    ),
                    false);
            }
            else {
                alertErrorMsg('Veuillez sélectionner un emplacement.', true);
            }
        });
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
        alertErrorMsg("Il n'y a aucun article à imprimer.");
    }
}
