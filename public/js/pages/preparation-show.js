$('.select2').select2();

let tableArticleSplitting;
let prepaHasBegun = false;
let $preparationId = $('#prepa-id');
let $modalSubmitPreparation = $('#modal-select-location');
let tables = [];
const showTargetLocationPicking = Number($(`input[name=showTargetLocationPicking]`).val());

$(function () {
    loadLogisticUnitList($preparationId.val());
    const $locationSelect = $modalSubmitPreparation.find('select[name="location"]');
    Select2Old.location($locationSelect);

    $(document).on('hidden.bs.modal','#modalSplitting', function () {
        $('.action-on-click-single').data('clicked', false);
    });

    let modalNewSensorPairing = $("#modalNewSensorPairing");
    let submitNewSensorPairing = $("#submitNewSensorPairing");
    let urlNewSensorPairing = Routing.generate('preparation_sensor_pairing_new', true)
    InitModal(modalNewSensorPairing, submitNewSensorPairing, urlNewSensorPairing, {
        success: () => {
            window.location.reload();
        }
    });

    initializeEdit();

    const $modalDeletePreparation = $('#modalDeletePreparation');
    const $submitDeletePreparation = $modalDeletePreparation.find(`.submit`);
    const pathDeletePreparation = Routing.generate(`preparation_delete`, {preparation: $modalDeletePreparation.find(`input[name=preparation]`).val()}, true);
    InitModal($modalDeletePreparation, $submitDeletePreparation, pathDeletePreparation);

    let urlEditLigneArticle = Routing.generate('prepa_edit_ligne_article', true);
    let modalEditLigneArticle = $("#modalEditLigneArticle");
    let submitEditLigneArticle = $("#submitEditLigneArticle");
    InitModal(modalEditLigneArticle, submitEditLigneArticle, urlEditLigneArticle, {
        success: ({needReload}) =>  needReload ? window.location.reload() : loadLogisticUnitList($preparationId.val())
    });
});

function loadLogisticUnitList(preparationId) {
    const $logisticUnitsContainer = $('.logistic-units-container');
    wrapLoadingOnActionButton($logisticUnitsContainer, () => (
        AJAX.route('GET', 'preparation_order_logistic_units_api', {id: preparationId})
            .json()
            .then(({html}) => {
                $logisticUnitsContainer.html(html);
                $logisticUnitsContainer.find('.articles-container table')
                    .each(function () {
                        const $table = $(this);
                        const table = initDataTable($table, {
                            serverSide: false,
                            ordering: true,
                            paging: false,
                            searching: false,
                            columns: [
                                {data: 'Actions', title: '', className: 'noVis', orderable: false},
                                {data: 'reference', title: 'Référence'},
                                {data: 'barcode', title: 'Code barre'},
                                {data: 'label', title: 'Libellé'},
                                {data: 'targetLocationPicking', title: 'Emplacement cible picking', visible: showTargetLocationPicking},
                                {data: 'quantity', title: 'Quantité en stock'},
                                {data: 'quantityToPick', title: 'Quantité à prélever'},
                                {data: 'pickedQuantity', title: 'Quantité prélevée'},
                            ],
                            rowConfig: {
                                needsRowClickAction: true,
                                needsColor: true,
                                color: 'success',
                                dataToCheck: 'active'
                            },
                            domConfig: {
                                removeInfo: true,
                            },
                            order: [['reference', "asc"]],
                        });

                        tables.push(table);
                    });
            })
        )
    );
}

function initializeEdit() {
    const $modalEditPreparation = $('#modalEditPreparation');
    const $submit = $modalEditPreparation.find('[type=submit]');
    Form
        .create($modalEditPreparation)
        .onSubmit(function (data) {
            wrapLoadingOnActionButton($submit, () => {
                return AJAX
                    .route('POST', 'preparation_edit')
                    .json(data)
                    .then(() => window.location.reload());
            });
        });
}

function startPicking($button, managementType) {
    if (!$button.data('clicked')) {
        $('.action-on-click-single').data('clicked', true);
        let ligneArticleId = $button.attr('value');

        let path = Routing.generate('start_splitting', true);
        $.post(path, JSON.stringify(ligneArticleId), function (html) {
            $('#splittingContent').html(html);
            let tableSplittingArticlesConfig = {
                'lengthMenu': [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Tout']],
                domConfig: {
                    needsPaginationRemoval: true
                },
                order: []
            };
            if (!showTargetLocationPicking && managementType) {
                tableSplittingArticlesConfig.order = [
                    4, "asc"
                ];
            }
            tableArticleSplitting = initDataTable('tableSplittingArticles', tableSplittingArticlesConfig);
            $('#modalSplitting').modal('show');
        });
    }
}

function submitSplitting($submit) {
    if (!$submit.hasClass('loading')) {
        const success = validatePreparationArticlesSplitting();
        if (success) {
            $submit.pushLoader('white');
            $submit.addClass('loading');
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

            AJAX
                .route(AJAX.POST, 'submit_splitting')
                .json( {
                    articles: chosenArticles,
                    quantite: $submit.data('qtt'),
                    demande: $submit.data('demande'),
                    refArticle: $submit.data('ref'),
                    preparation: $submit.data('prep')
                })
                .then(({success, needReload}) => {
                    if (needReload) {
                        window.location.reload();
                    } else if (success) {
                        $('#modalSplitting').modal('hide');
                        loadLogisticUnitList($preparationId.val());
                    } else {
                        showBSAlert(message, 'danger');
                    }
                })
        }
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

function validatePreparationArticlesSplitting() {
    const $tableSplittingArticles = $(`#tableSplittingArticles`);
    const $inputs = $tableSplittingArticles.find('.input');
    const $modal = $tableSplittingArticles.closest(`.modal`);
    const quantityToTake = $modal.find(`input[name=quantityToTake]`).val();
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
    let data = [];
    tables.forEach((table) => {
        data.push(Array.from(table.column('pickedQuantity:name').data()));
    });

    const canProceed = data
        .flat()
        .filter(value => typeof value === 'number')
        .reduce((total, elem) => total + elem, 0) > 0;
    if (!canProceed) {
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
                            url: Routing.generate('preparation_finish', {'preparation': $preparationId.val()}),
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
    const $printButton = $('.print-button');
    const loading = $printButton.data('loading');
    if (!loading) {
        $printButton.data('loading', true);
        AJAX.route(AJAX.GET, 'count_bar_codes', {preparation: $preparationId.val()})
            .json()
            .then(({result}) => {
                $printButton.data('loading', false);
                if (result) {
                    AJAX
                        .route(AJAX.GET, 'preparation_bar_codes_print', {
                            preparation: $preparationId.val(),
                            forceTagEmpty: true,
                        })
                        .file({
                            success: "Impression des étiquettes terminée.",
                            error: "Il n'y a aucune étiquette à imprimer."
                        });
                } else {
                    showBSAlert("Il n'y a aucune étiquette à imprimer", 'info');
                }
            });
    }
}
function clearValidatePreparationModal() {
    const $locationSelect = $modalSubmitPreparation.find('select[name="location"]')
    $locationSelect.html('');
    $locationSelect.val('');
}

function printLogisticUnit(id) {
    Wiistock.download(Routing.generate(`pack_print_single`, {pack: id}));
}

function printArticles(preparation) {
    let templates;
    try {
        templates = JSON.parse($('#tagTemplates').val());
    } catch (error) {
        templates = [];
    }
    const params = {
        preparation
    };

    if (templates.length > 0) {
        Promise.all(
            [AJAX.route('GET', `preparation_bar_codes_print`, {forceTagEmpty: true, ...params}).file({})]
                .concat(templates.map(function (template) {
                    params.template = template;
                    return AJAX
                        .route('GET', `preparation_bar_codes_print`, params)
                        .file({})
                }))
        ).then(() => Flash.add('success', 'Impression des étiquettes terminée.'));
    } else {
        AJAX
            .route(AJAX.GET, 'preparation_bar_codes_print', {
                forceTagEmpty: true,
                ...params,
            })
            .file({
                success: "Impression des étiquettes terminée.",
                error: "Il n'y a aucune étiquette à imprimer."
            });
    }
}

function treatLine(event, $line) {
    if(!$(event.target).closest(`.no-action-click`).exists()) {
        const $datatableContainer = $line.closest(`.logistic-unit-wrapper`).find(`.dataTables_wrapper`)[0];
        const table = tables.filter((table) => table.table().container() === $datatableContainer);
        const values = Array.from(table[0].data())
            .map(({lineId, quantityToPick}) => [{ligneArticle: lineId, quantite: quantityToPick}])
            .flat();

        wrapLoadingOnActionButton($line, () => (
            AJAX.route(`GET`, `prepa_edit_ligne_article`, {values: JSON.stringify(values)})
                .json()
                .then(({needReload}) => {
                    Flash.add(`success`, `Les articles de l'unité logistique ont bien été préparés.`);
                    loadLogisticUnitList($preparationId.val());
                    if(needReload){
                        window.location.reload();
                    }
                })
        ));
    }
}
