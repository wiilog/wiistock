let editorNewArticleAlreadyDone = false;
let editorNewReferenceArticleAlreadyDone = false;
let editorNewLivraisonAlreadyDoneForDL = false;
let tableArticle;
let tableLitigesReception;
let modalNewLigneReception = "#modalNewLigneReception";
let $modalNewLigneReception = $(modalNewLigneReception);

$(function () {
    $('.select2').select2();
    const dataTableInitRes = InitPageDataTable();
    tableArticle = dataTableInitRes.tableArticle;
    tableLitigesReception = dataTableInitRes.tableLitigesReception;
    InitiliserPageModals();
    $('#packing-package-number, #packing-number-in-package').on('keypress keydown keyup', function () {
        if ($(this).val() === '' || $(this).val() < 0) {
            $(this).val('');
        }
    });
});

function InitiliserPageModals() {
    let modal = $("#modalAddLigneArticle");
    let submit = $("#addArticleLigneSubmit");
    let url = Routing.generate('reception_article_add', true);
    InitialiserModal(modal, submit, url, tableArticle, createHandlerAddLigneArticleResponse(modal), false, false);

    let modalDeleteArticle = $("#modalDeleteLigneArticle");
    let submitDeleteArticle = $("#submitDeleteLigneArticle");
    let urlDeleteArticle = Routing.generate('reception_article_remove', true);
    InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

    let modalEditArticle = $("#modalEditLigneArticle");
    let submitEditArticle = $("#submitEditLigneArticle");
    let urlEditArticle = Routing.generate('reception_article_edit', true);
    InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle, displayErrorReception, false, false);

    let ModalDelete = $("#modalDeleteReception");
    let SubmitDelete = $("#submitDeleteReception");
    let urlDeleteReception = Routing.generate('reception_delete', true);
    InitialiserModal(ModalDelete, SubmitDelete, urlDeleteReception);

    let modalModifyReception = $('#modalEditReception');
    let submitModifyReception = $('#submitEditReception');
    let urlModifyReception = Routing.generate('reception_edit', true);
    InitialiserModal(modalModifyReception, submitModifyReception, urlModifyReception);

    let modalNewLitige = $('#modalNewLitige');
    let submitNewLitige = $('#submitNewLitige');
    let urlNewLitige = Routing.generate('litige_new_reception', true);
    initModalWithAttachments(modalNewLitige, submitNewLitige, urlNewLitige, tableLitigesReception);

    let modalEditLitige = $('#modalEditLitige');
    let submitEditLitige = $('#submitEditLitige');
    let urlEditLitige = Routing.generate('litige_edit_reception', true);
    initModalWithAttachments(modalEditLitige, submitEditLitige, urlEditLitige, tableLitigesReception);

    let ModalDeleteLitige = $("#modalDeleteLitige");
    let SubmitDeleteLitige = $("#submitDeleteLitige");
    let urlDeleteLitige = Routing.generate('litige_delete_reception', true);
    InitialiserModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, tableLitigesReception);
}

function InitPageDataTable() {
    let pathAddArticle = Routing.generate('reception_article_api', {'id': $('input[type="hidden"]#receptionId').val()}, true);
    let pathLitigesReception = Routing.generate('litige_reception_api', {reception: $('#receptionId').val()}, true);
    let tableArticleConfig = {
        "lengthMenu": [5, 10, 25],
        ajax: {
            "url": pathAddArticle,
            "type": "POST",
            dataSrc: ({data, hasBarCodeToPrint}) => {
                const $printButton = $('#buttonPrintMultipleBarcodes');
                const dNoneClass = 'd-none';
                if (hasBarCodeToPrint) {
                    $printButton.removeClass(dNoneClass);
                    $('.print').removeClass(dNoneClass)
                } else {
                    $printButton.addClass(dNoneClass);
                    $('.print').addClass(dNoneClass)
                }
                return data;
            }
        },
        domConfig: {
            removeInfo: true
        },
        order: [[5, "desc"], [1, "desc"]],
        columns: [
            {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Référence', 'title': 'Référence'},
            {"data": 'Commande', 'title': 'Commande'},
            {"data": 'A recevoir', 'title': 'A recevoir'},
            {"data": 'Reçu', 'title': 'Reçu'},
            {"data": 'Urgence', 'title': 'Urgence', visible: false},
            {"data": 'Comment', 'title': 'Comment', visible: false},
        ],
        rowConfig: {
            needsRowClickAction: true,
            needsColor: true,
            dataToCheck: 'Urgence',
            color: 'danger',
            callback: (row, data) => {
                if (data.Urgence && data.Comment) {
                    const $row = $(row);
                    $row.attr('title', data.Comment);
                    initTooltips($row);
                }
            }
        },
    };
    let tableLitigeConfig = {
        "lengthMenu": [5, 10, 25],
        ajax: {
            "url": pathLitigesReception,
            "type": "POST",
        },
        columns: [
            {"data": 'actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'type', 'name': 'type', 'title': 'Type'},
            {"data": 'status', 'name': 'status', 'title': 'Statut'},
            {"data": 'lastHistoric', 'name': 'lastHistoric', 'title': 'Dernier historique'},
            {"data": 'date', 'name': 'date', 'title': 'Date', visible: false},
            {"data": 'urgence', 'name': 'urgence', 'title': 'urgence', visible: false},
        ],
        order: [
            [5, 'desc'],
            [4, 'desc'],
        ],
        domConfig: {
            removeInfo: true
        },
        rowConfig: {
            needsRowClickAction: true,
            needsColor: true,
            dataToCheck: 'urgence',
            color: 'danger',
        },
    };
    return {
        tableArticle: initDataTable('tableArticle_id', tableArticleConfig),
        tableLitigesReception: initDataTable('tableReceptionLitiges', tableLitigeConfig)
    };
}

function initEditReception() {
    initDateTimePickerReception();
    initOnTheFlyCopies($('.copyOnTheFly'));
}

function initDateTimePickerReception() {
    initDateTimePicker('#dateCommande, #dateAttendue');
    $('.date-cl').each(function () {
        initDateTimePicker('#' + $(this).attr('id'));
    });
}

function displayErrorReception(data) {
    let $modal = $("#modalEditLigneArticle");
    let msg = 'La quantité reçue ne peut pas être supérieure à la quantité à recevoir !';
    displayError($modal, msg, data);
}

function editRowLitigeReception(button, afterLoadingEditModal = () => {
}, receptionId, litigeId) {
    let path = Routing.generate('litige_api_edit_reception', true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');

    let params = {
        litigeId: litigeId,
        reception: receptionId
    };

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        ajaxAutoArticlesReceptionInit(modal.find('.select2-autocomplete-articles'));
        fillDemandeurField(modal);
        let values = [];
        data.colis.forEach(val => {
            values.push({
                id: val.id,
                text: val.text
            })
        });
        values.forEach(value => {
            $('#colisEditLitige').select2("trigger", "select", {
                data: value
            });
        });
        modal.find('#acheteursLitigeEdit').val(data.acheteurs).select2();
        afterLoadingEditModal()
    }, 'json');

    modal.find(submit).attr('value', litigeId);
}

function getCommentAndAddHisto() {
    let path = Routing.generate('add_comment', {litige: $('#litigeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function (response) {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}

function openTableHisto() {
    let pathHistoLitige = Routing.generate('histo_litige_api', {litige: $('#litigeId').val()}, true);
    let tableHistoLitigeConfig = {
        ajax: {
            "url": pathHistoLitige,
            "type": "POST"
        },
        columns: [
            {"data": 'user', 'name': 'Utilisateur', 'title': 'Utilisateur'},
            {"data": 'date', 'name': 'date', 'title': 'Date'},
            {"data": 'commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        domConfig: {
            needsPartialDomOverride: true
        },
    };
    tableHistoLitige = initDataTable('tableHistoLitige', tableHistoLitigeConfig);
}

function initDatatableConditionnement() {
    let pathArticle = Routing.generate('article_by_reception_api', true);
    let tableFromArticleConfig = {
        info: false,
        paging: false,
        searching: false,
        destroy: true,
        ajax: {
            "url": pathArticle,
            "type": "POST",
            "data": function () {
                return {
                    'ligne': $('#ligneSelected').val()
                }
            },
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        order: [1, 'asc'],
        columns: [
            {"data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis'},
            {"data": 'Code barre', 'name': 'Code barre', 'title': 'Code article'},
            {"data": "Statut", 'name': 'Statut', 'title': 'Statut'},
            {"data": 'Libellé', 'name': 'Libellé', 'title': 'Libellé'},
            {"data": 'Référence article', 'name': 'Référence article', 'title': 'Référence article'},
            {"data": 'Quantité', 'name': 'Quantité', 'title': 'Quantité'},
        ],
        aoColumnDefs: [{
            'sType': 'natural',
            'bSortable': true,
            'aTargets': [1]
        }],
        columnDefs: [
            {
                orderable: false,
                targets: 0
            }
        ]
    };
    let tableFromArticle = initDataTable('tableArticleInner_id', tableFromArticleConfig);

    let statutVisible = $("#statutVisible").val();
    if (!statutVisible) {
        tableFromArticle.column('Statut:name').visible(false);
    }

    initModalCondit(tableFromArticle);
}

function initModalCondit(tableFromArticle) {
    let modalEditInnerArticle = $("#modalEditArticle");
    let submitEditInnerArticle = $("#submitEditArticle");
    let urlEditInnerArticle = Routing.generate('article_edit', true);
    InitialiserModal(modalEditInnerArticle, submitEditInnerArticle, urlEditInnerArticle, tableFromArticle);

    let modalDeleteInnerArticle = $("#modalDeleteArticle");
    let submitDeleteInnerArticle = $("#submitDeleteArticle");
    let urlDeleteInnerArticle = Routing.generate('article_delete', true);
    InitialiserModal(modalDeleteInnerArticle, submitDeleteInnerArticle, urlDeleteInnerArticle, tableFromArticle);
}

function initNewArticleEditor(modal) {
    const $modal = $(modal);
    let $select2refs = $modal.find('[name="referenceArticle"]');
    ajaxAutoRefArticleInit($select2refs);

    if (!editorNewArticleAlreadyDone) {
        initEditorInModal(modal);
        editorNewArticleAlreadyDone = true;
    }
    clearAddRefModal();
    clearModal(modal);

    const $commandField = $(modal).find('[name="commande"]');
    const numCommand = $('#numCommandeReception').val();
    $commandField.val(numCommand);

    setTimeout(() => {
        openSelect2($select2refs);
    }, 400);
}

function openModalArticlesFromLigneArticle(ligneArticleId) {
    $('#ligneSelected').val(ligneArticleId);
    $('#chooseConditionnement').click();
    initDatatableConditionnement();
}

function articleChanged(select) {
    if (select.val() !== null) {
        let route = Routing.generate('is_urgent', true);
        let params = JSON.stringify(select.val());
        $.post(route, params, function (response) {
            if (response.urgent) {
                $('.emergency').removeClass('d-none');
                $('.emergency-comment').text(response.comment);
            } else {
                $('.emergency').addClass('d-none');
            }
            $('.body-add-ref').css('display', 'flex');
            $('#innerNewRef').html('');
        });
    }
}

function toggleRequiredChampsFixes(button) {
    displayRequiredChampsFixesByTypeQuantiteReferenceArticle(button.data('title'), button);
}

function initNewReferenceArticleEditor() {
    if (!editorNewReferenceArticleAlreadyDone) {
        initEditor('.editor-container-new');
        editorNewReferenceArticleAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
    let modalRefArticleNew = $("#new-ref-inner-body");
    let submitNewRefArticle = $("#submitNewRefArticleFromRecep");
    let urlRefArticleNew = Routing.generate('reference_article_new', true);
    InitialiserModalRefArticleFromRecep(modalRefArticleNew, submitNewRefArticle, urlRefArticleNew, false);
}

function addArticle() {
    let path = Routing.generate('get_modal_new_ref', true);
    $.post(path, {}, function (modalNewRef) {
        $('#innerNewRef').html(modalNewRef);
        initNewReferenceArticleEditor();
    });
}

function finishReception(receptionId, confirmed) {
    $.post(Routing.generate('reception_finish'), JSON.stringify({
        id: receptionId,
        confirmed: confirmed
    }), function (data) {
        if (data === 1) {
            window.location.href = Routing.generate('reception_index', true);
        } else if (data === 0) {
            $('#finishReception').click();
        } else {
            alertErrorMsg(data);
        }
    }, 'json');
}

function clearAddRefModal() {
    $('#innerNewRef').html('');
    $('.body-add-ref').css('display', 'none');
}

function InitialiserModalRefArticleFromRecep(modal, submit, path, close = true) {
    submit.click(function () {
        submitActionRefArticleFromRecep(modal, path, close);
    });
}

function afterLoadingEditModal($button) {
    initRequiredChampsFixes($button);
}

function submitActionRefArticleFromRecep(modal, path, close) {
    let {Data, missingInputs, wrongNumberInputs, doublonRef} = getDataFromModalReferenceArticle(modal);
    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && !doublonRef) {
        if (close == true) modal.find('.close').click();
        $.post(path, JSON.stringify(Data), function (data) {
            if (data.success) {
                $('#innerNewRef').html('');
                modal.find('.error-msg').html('');
            } else {
                modal.find('.error-msg').html(data.msg);
            }
        });
    } else {
        // ... sinon on construit les messages d'erreur
        let msg = buildErrorMsgReferenceArticle(missingInputs, wrongNumberInputs, doublonRef);
        modal.find('.error-msg').html(msg);
    }
}

function clearModalLigneReception(modal) {
    const $modal = $(modal);
    $modal
        .find('.articles-conditionnement-container')
        .html('');

    $modal
        .find('#packing-package-number, #packing-number-in-package')
        .val('');

    let $submitNewReceptionButton = $modal.find("#submitNewReceptionButton");
    $submitNewReceptionButton.off('click');

    const $select2 = $modal.find('select[name="refArticleCommande"]');
    if ($select2.hasClass("select2-hidden-accessible")) {
        $select2
            .val(null)
            .html('');
        $select2.select2('data', null);
        $select2.select2('destroy');
    }
    $('.packing-title').addClass('d-none');
    clearModal(modal);

    toggleDLForm();
    resetDefaultArticleFournisseur();
}

function validatePacking($button) {
    const $packingContainer = $button.closest('.bloc-packing');
    const $selectRefArticle = $packingContainer.find('[name="refArticleCommande"]');
    const $selectArticleFournisseur = $packingContainer.find('[name="articleFournisseurDefault"]');
    const packageNumber = Number($packingContainer
        .find('[name="packageNumber"]')
        .val());
    const numberInPackage = Number($packingContainer
        .find('[name="numberInPackage"]')
        .val());
    const selectedOptionArray = $selectRefArticle.select2('data');
    const [defaultArticleFournisseur] = $selectArticleFournisseur.select2('data');

    if ((selectedOptionArray && selectedOptionArray.length > 0) &&
        (packageNumber && packageNumber > 0) &&
        (numberInPackage && numberInPackage > 0)) {
        const selectedOption = selectedOptionArray[0];

        $.get(
            Routing.generate('get_ligne_article_conditionnement', true),
            {
                quantity: numberInPackage,
                reference: selectedOption.reference,
                commande: selectedOption.commande,
                defaultArticleFournisseurReference: defaultArticleFournisseur && defaultArticleFournisseur.text
            },
            'text/html'
        ).done(function (html) {
            const $html = $(html);
            const $lastContainerArticle = $packingContainer.find('.articles-conditionnement-container .conditionnement-article:last-child');
            const lastIndex = $lastContainerArticle.length > 0 ? $lastContainerArticle.data('multiple-object-index') : -1;
            const initIndex = lastIndex + 1;
            for (let index = initIndex; index < (initIndex + packageNumber); index++) {
                const $clonedHtml = $html.clone();

                const $containerArticle = $('<div/>', {
                    class: 'conditionnement-article',
                    'data-multiple-key': 'conditionnement',
                    'data-multiple-object-index': index,
                    html: $clonedHtml
                });

                $packingContainer
                    .find('.articles-conditionnement-container')
                    .append($containerArticle);

                $packingContainer.find('.packing-title').removeClass('d-none');
            }

            let $listMultiple = $packingContainer.find('.list-multiple');
            $listMultiple.select2();
        })
    }
}

function initNewLigneReception() {
    if (!editorNewLivraisonAlreadyDoneForDL) {
        initEditorInModal(modalNewLigneReception);
        editorNewLivraisonAlreadyDoneForDL = true;
    }
    initSelect2($modalNewLigneReception.find('.ajax-autocompleteEmplacement'), '', 1, {route: 'get_emplacement'});
    initSelect2($('.select2-type'));
    initSelect2($modalNewLigneReception.find('.select2-user'), '', 1, {route: 'get_user'});
    initSelect2($modalNewLigneReception.find('.select2-autocomplete-ref-articles'), '', 0, {
        route: 'get_ref_article_reception',
        param: {reception: $('#receptionId').val()}
    });
    if ($('#locationDemandeLivraison').length > 0) {
        initDisplaySelect2Multiple('#locationDemandeLivraison', '#locationDemandeLivraisonValue');
    }
    let urlNewLigneReception = Routing.generate(
        'reception_new_with_packing',
        {reception: $modalNewLigneReception.find('input[type="hidden"][name="reception"]').val()},
        true
    );
    let $submitNewReceptionButton = $modalNewLigneReception.find("#submitNewReceptionButton");

    $submitNewReceptionButton.click(function () {
        const error = getErrorModalNewLigneReception();
        const $errorContainer = $modalNewLigneReception.find('.error-msg');
        if (error) {
            $errorContainer.text(error);
        } else {
            $errorContainer.text('');
            submitAction($modalNewLigneReception, urlNewLigneReception, tableArticle, function (success) {
                if (success) {
                    const $printButton = $('#buttonPrintMultipleBarcodes');
                    if ($printButton.length > 0) {
                        window.location.href = $printButton.attr('href');
                    }
                }
            });
        }
    });

    let $typeContentNewChildren = $('#typeContentNew').children();
    $typeContentNewChildren.addClass('d-none');
    $typeContentNewChildren.removeClass('d-block');
}


function getErrorModalNewLigneReception() {
    // on vérifie qu'au moins un conditionnement a été fait
    const articlesConditionnement = $modalNewLigneReception
        .find('.articles-conditionnement-container')
        .children();

    // on vérifie que les quantités sont correctes
    const quantityError = getQuantityErrorModalNewLigneReception();

    let msg = undefined;
    if (articlesConditionnement.length === 0) {
        msg = 'Veuillez effectuer un conditionnement.';
    } else if (quantityError) {
        let s = quantityError.quantity > 1 ? 's' : '';
        msg = `Vous ne pouvez pas conditionner plus de ${quantityError.quantity} article${s} pour cette référence ${quantityError.reference} – ${quantityError.commande}.`;
    }

    return msg;
}

function getQuantityErrorModalNewLigneReception() {
    const $conditionnementArticleArray = $modalNewLigneReception.find('.articles-conditionnement-container .conditionnement-article');
    const quantityByConditionnementArray = [];

    $conditionnementArticleArray.each(function () {
        const $conditionnement = $(this);

        const referenceConditionnement = $conditionnement.find('input[name="refRefArticle"]').val();
        const noCommandeConditionnement = $conditionnement.find('input[name="noCommande"]').val();
        const quantityConditionnement = Number($conditionnement.find('input[name="quantite"]').val());
        const quantityByConditionnement = quantityByConditionnementArray.find(({reference, noCommande}) => (
            (reference === referenceConditionnement) &&
            (noCommande === noCommandeConditionnement)
        ));
        if (!quantityByConditionnement) {
            quantityByConditionnementArray.push({
                reference: referenceConditionnement,
                noCommande: noCommandeConditionnement,
                quantity: quantityConditionnement
            })
        } else {
            quantityByConditionnement.quantity += quantityConditionnement;
        }
    });

    const dataDatatable = tableArticle.rows().data();
    let indexDatatable = 0;
    let quantityError;
    while ((indexDatatable < dataDatatable.length) && !quantityError) {
        const currentLineReference = dataDatatable[indexDatatable]['Référence'];
        const currentLineCommande = dataDatatable[indexDatatable]['Commande'];
        const currentLineQuantity = dataDatatable[indexDatatable]['A recevoir'] - Number(dataDatatable[indexDatatable]['Reçu'] || 0);
        const quantityByConditionnement = quantityByConditionnementArray.find(({reference, noCommande}) => (
            (reference === currentLineReference) &&
            (noCommande === currentLineCommande)
        ));

        if (quantityByConditionnement && quantityByConditionnement.quantity > currentLineQuantity) {
            quantityError = {
                reference: currentLineReference,
                commande: currentLineCommande,
                quantity: Number(currentLineQuantity)
            };
        } else {
            indexDatatable++;
        }
    }
    return quantityError;
}

function removePackingItem($button) {
    $button.closest('.conditionnement-article').remove();
}

function createHandlerAddLigneArticleResponse($modal) {
    return (data) => {
        if (data.errorMsg) {
            alertErrorMsg(data.errorMsg, true);
        } else {
            alertSuccessMsg('La référence a été ajoutée à la réception', true);
            $modal.find('.close').click();
            clearModal($modal);
        }
    }
}

function updateQuantityToReceive($input) {
    $input.closest('.modal').find('[name="quantite"]').attr('max', $input.val());
}

function toggleDLForm() {
    const $input = $('#modalNewLigneReception input[name="create-demande"]');
    const $demandeForm = $input
        .parents('form')
        .find('.demande-form');
    if ($input.is(':checked')) {
        $demandeForm.removeClass('d-none');
        $demandeForm.find('.data').attr('disabled', null);
    }
    else {
        $demandeForm.addClass('d-none');
        $demandeForm.find('.data').attr('disabled', 'disabled');
    }
}

function initConditionnementArticleFournisseurDefault() {
    const $selectRefArticle = $('#modalNewLigneReception select[name="refArticleCommande"]');
    const [referenceArticle] = $selectRefArticle.select2('data');
    const $selectArticleFournisseur = $('#modalNewLigneReception select[name="articleFournisseurDefault"]');

    if (referenceArticle) {
        console.log(referenceArticle);
        resetDefaultArticleFournisseur(true);
        initSelect2(
            $selectArticleFournisseur,
            '',
            1,
            {
                route: 'get_article_fournisseur_autocomplete',
                param: {
                    referenceArticle: referenceArticle.reference
                }
            },
            {},
            referenceArticle.defaultArticleFournisseur || {});
    }
    else {
        resetDefaultArticleFournisseur();
    }
}

function resetDefaultArticleFournisseur(show = false) {
    const $selectArticleFournisseur = $('#modalNewLigneReception select[name="articleFournisseurDefault"]');
    const $selectArticleFournisseurFormGroup = $selectArticleFournisseur.parents('.form-group');
    if ($selectArticleFournisseur.hasClass("select2-hidden-accessible")) {
        $selectArticleFournisseur.select2('destroy');
    }
    $selectArticleFournisseur.val(null).trigger('change');

    if (show) {
        $selectArticleFournisseurFormGroup.removeClass('d-none');
    }
    else {
        $selectArticleFournisseurFormGroup.addClass('d-none');
    }
}
