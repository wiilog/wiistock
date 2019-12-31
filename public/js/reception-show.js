let editorNewArticleAlreadyDone = false;
let editorNewReferenceArticleAlreadyDone = false;
let editorNewLivraisonAlreadyDoneForDL = false;
let tableArticle;
let tableLitigesReception;

$(function () {
    const dataTableInitRes = InitiliaserPageDataTable();
    tableArticle = dataTableInitRes.tableArticle;
    tableLitigesReception = dataTableInitRes.tableLitigesReception;
    InitiliserPageModals();
});

function InitiliserPageModals() {
    let modal = $("#modalAddLigneArticle");
    let submit = $("#addArticleLigneSubmit");
    let url = Routing.generate('reception_article_add', true);
    InitialiserModal(modal, submit, url, tableArticle);

    let modalDeleteArticle = $("#modalDeleteLigneArticle");
    let submitDeleteArticle = $("#submitDeleteLigneArticle");
    let urlDeleteArticle = Routing.generate('reception_article_remove', true);
    InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

    let modalEditArticle = $("#modalEditLigneArticle");
    let submitEditArticle = $("#submitEditLigneArticle");
    let urlEditArticle = Routing.generate('reception_article_edit', true);
    InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);


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

function InitiliaserPageDataTable() {
    let pathAddArticle = Routing.generate('reception_article_api', {'id': $('input[type="hidden"]#receptionId').val()}, true);
    let pathLitigesReception = Routing.generate('litige_reception_api', {reception: $('#receptionId').val()}, true);

    return {
        tableArticle: $('#tableArticle_id').DataTable({
            responsive: true,
            "lengthMenu": [5, 10, 25],
            language: {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax: {
                "url": pathAddArticle,
                "type": "POST"
            },
            order: [[1, "desc"]],
            columns: [
                {"data": 'Actions', 'title': 'Actions'},
                {"data": 'Référence', 'title': 'Référence'},
                {"data": 'Commande', 'title': 'Commande'},
                {"data": 'A recevoir', 'title': 'A recevoir'},
                {"data": 'Reçu', 'title': 'Reçu'},
                {"data": 'Urgence', 'title': 'Urgence'},
            ],
            columnDefs: [
                {"orderable": false, "targets": 0},
                {"visible": false, "targets": 5}
            ],
            rowCallback: function (row, data) {
                $(row).addClass(data.Urgence ? 'table-danger' : '');
            }
        }),
        tableLitigesReception: $('#tableReceptionLitiges').DataTable({
            responsive: true,
            language: {
                url: "/js/i18n/dataTableLanguage.json",
            },
            "lengthMenu": [5, 10, 25],
            scrollX: true,
            ajax: {
                "url": pathLitigesReception,
                "type": "POST",
            },
            columns: [
                {"data": 'actions', 'name': 'Actions', 'title': 'Actions'},
                {"data": 'type', 'name': 'type', 'title': 'Type'},
                {"data": 'status', 'name': 'status', 'title': 'Statut'},
                {"data": 'lastHistoric', 'name': 'lastHistoric', 'title': 'Dernier historique'},
                {"data": 'date', 'name': 'date', 'title': 'Date'},
            ],
            columnDefs: [
                {
                    "type": "customDate",
                    "targets": 4,
                    "visible": false
                },
                {
                    orderable: false,
                    targets: 0
                }
            ],
            order: [
                [4, 'desc'],
            ],
        })
    };
}



function editRowLitige(button, afterLoadingEditModal = () => {}, receptionId, litigeId) {
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
    tableHistoLitige = $('#tableHistoLitige').DataTable({
        language: {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathHistoLitige,
            "type": "POST"
        },
        columns: [
            {"data": 'user', 'name': 'Utilisateur', 'title': 'Utilisateur'},
            {"data": 'date', 'name': 'date', 'title': 'Date'},
            {"data": 'commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
        ],
        dom: '<"top">rt<"bottom"lp><"clear">'
    });
}

function initDatatableConditionnement() {
    let pathArticle = Routing.generate('article_by_reception_api', true);
    let tableFromArticle = $('#tableArticleInner_id').DataTable({
        info: false,
        paging: false,
        "language": {
            url: "/js/i18n/dataTableLanguage.json",
        },
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
        columns: [
            {"data": 'Code', 'name': 'Code', 'title': 'Code article'},
            {"data": "Statut", 'name': 'Statut', 'title': 'Statut'},
            {"data": 'Libellé', 'name': 'Libellé', 'title': 'Libellé'},
            {"data": 'Référence article', 'name': 'Référence article', 'title': 'Référence article'},
            {"data": 'Quantité', 'name': 'Quantité', 'title': 'Quantité'},
            {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'}
        ],
        aoColumnDefs: [{
            'sType': 'natural',
            'bSortable': true,
            'aTargets': [0]
        }],
        columnDefs: [
            {
                orderable: false,
                targets: 5
            }
        ]
    });

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
    ajaxAutoRefArticleInit($('.ajax-autocomplete'));

    if (!editorNewArticleAlreadyDone) {
        initEditorInModal(modal);
        editorNewArticleAlreadyDone = true;
    }
    clearAddRefModal();
}

function printSingleBarcode(button) {
    let params = {
        'ligne': button.data('id')
    };
    $.post(Routing.generate('get_ligne_from_id'), JSON.stringify(params), function (response) {
        if (!response.article) {
            printBarcodes(
                [response.ligneRef],
                response,
                'Etiquette concernant l\'article ' + response.ligneRef + '.pdf',
                [response.barcodeLabel]
            );
        } else {
            $('#ligneSelected').val(button.data('id'));
            $('#chooseConditionnement').click();
            let $submit = $('#submitConditionnement');
            $submit.attr('data-ref', response.article)
            $submit.attr('data-id', button.data('id'))
            initDatatableConditionnement();
            $submit.addClass('d-none');
            $('#reference-list').html(response.article);
        }
    });
}

function articleChanged(select) {
    if (select.val() !== null) {
        let route = Routing.generate('is_urgent', true);
        let params = JSON.stringify(select.val());
        $.post(route, params, function (response) {
            if (response) {
                $('.emergency').removeClass('d-none');
            } else {
                $('.emergency').addClass('d-none');
            }
            $('.body-add-ref').css('display', 'flex');
            $('#innerNewRef').html('');
        });
    }
}

function toggleRequiredChampsFixes(button) {
    displayRequiredChampsFixesByTypeQuantiteReferenceArticle(button.data('title'));
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
    InitialiserModalRefArticleFromRecep(modalRefArticleNew, submitNewRefArticle, urlRefArticleNew, displayErrorRA, false);
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

function InitialiserModalRefArticleFromRecep(modal, submit, path, callback = function () {}, close = true) {
    submit.click(function () {
        submitActionRefArticleFromRecep(modal, path, callback, close);
    });
}

function afterLoadingEditModal($button) {
    toggleRequiredChampsLibres($button, 'edit');
    initRequiredChampsFixes($button);
}

function submitActionRefArticleFromRecep(modal, path, callback = null, close = true) {
    let {Data, missingInputs, wrongNumberInputs, doublonRef} = getDataFromModalReferenceArticle(modal);
    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && !doublonRef) {
        if (close == true) modal.find('.close').click();
        $.post(path, JSON.stringify(Data), function (data) {
            if (data.success) $('#innerNewRef').html('');
            else modal.find('.error-msg').html('');
        });
        modal.find('.error-msg').html('');

    } else {
        // ... sinon on construit les messages d'erreur
        let msg = buildErrorMsgReferenceArticle(missingInputs, wrongNumberInputs, doublonRef);
        modal.find('.error-msg').html(msg);
    }

}


function ajaxAutoRefArticlesReceptionInit(select) {
    select.select2({
        ajax: {
            url: Routing.generate('get_ref_article_reception', {reception: $('#receptionId').val()}, true),
            dataType: 'json',
            delay: 250,
        },
        language: {
            searching: function () {
                return 'Recherche en cours...';
            },
            noResults: function () {
                return 'Aucun résultat.';
            }
        },
    });
}

function clearModalLigneReception(modal) {
    const $modal = $(modal);
    $modal
        .find('.articles-conditionnement-container')
        .html('');

    $modal
        .find('#packing-package-number, #packing-number-in-package')
        .val('');

    const $select2 = $modal.find('select[name="refArticleCommande"]');
    if ($select2.hasClass("select2-hidden-accessible")) {
        $select2
            .val(null)
            .html('');
        $select2.select2('data', null);
        $select2.select2('destroy');
    }
    ajaxAutoRefArticlesReceptionInit($select2);
    $('.packing-title').addClass('d-none');
    clearModal(modal);
}

function validatePacking($button) {
    const $packingContainer = $button.closest('.bloc-packing');
    const $selectRefArticle = $packingContainer.find('[name="refArticleCommande"]');
    const packageNumber = Number($packingContainer
        .find('[name="packageNumber"]')
        .val());
    const numberInPackage = Number($packingContainer
        .find('[name="numberInPackage"]')
        .val());
    const selectedOptionArray = $selectRefArticle.select2('data');

    if ((selectedOptionArray && selectedOptionArray.length > 0) &&
        (packageNumber && packageNumber > 0) &&
        (numberInPackage && numberInPackage > 0)) {
        const selectedOption = selectedOptionArray[0];

        $.get(
            Routing.generate('get_ligne_article_conditionnement', true),
            {
                quantity: numberInPackage,
                reference: selectedOption.reference,
                commande: selectedOption.commande
            },
            'text/html'
        ).done(
            function (html) {
                const $html = $(html);
                for (let index = 0; index < packageNumber; index++) {
                    const $clonedHtml = $html.clone();
                    const $articleFournisseur = $clonedHtml.find('select[name="articleFournisseur"]');
                    ajaxAutoArticleFournisseurByRefInit(selectedOption.reference, $articleFournisseur);

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
            }
        )
    }
}

function initNewLigneReception(modal) {
    if (!editorNewLivraisonAlreadyDoneForDL) {
        initEditorInModal(modal);
        editorNewLivraisonAlreadyDoneForDL = true;
    }
    initWithPH($('.ajax-autocompleteEmplacement'), 'Destination...', true, Routing.generate('get_emplacement'));
    initWithPH($('.select2-type'), 'Type...', false);
    initWithPH($('.select2-user'), 'Demandeur...', true, Routing.generate('get_user'));
    let urlNewLigneReception = Routing.generate(
        'reception_new_with_packing',
        {reception: $(modal).find('input[type="hidden"][name="reception"]').val()},
        true
    );
    let $modalNewLigneReception = $("#modalNewLigneReception");
    let $submitNewReceptionButton = $("#submitNewReceptionButton");

    $submitNewReceptionButton.click(function () {
        const error = getErrorModalNewLigneReception();
        const $errorContainer = $modalNewLigneReception.find('.error-msg');
        if (error) {
            $errorContainer.text(error);
        } else {
            $errorContainer.text('');
            submitAction($modalNewLigneReception, urlNewLigneReception, tableArticle);
        }
    });

    let $typeContentNewChildren = $('#typeContentNew').children();
    $typeContentNewChildren.addClass('d-none');
    $typeContentNewChildren.removeClass('d-block');
}


function getErrorModalNewLigneReception() {
    let $modalNewLigneReception = $("#modalNewLigneReception");
    // On check si au moins un conditionnement a été fait
    const articlesCondtionnement = $modalNewLigneReception
        .find('.articles-conditionnement-container')
        .children();

    const quantityError = getQuantityErrorModalNewLigneReception();

    return (articlesCondtionnement.length === 0)
        ? 'Veuillez effectuer un conditionnement.'
        : (quantityError && quantityError)
            ? `Vous ne pouvez pas conditionner plus de ${quantityError.quantity} article(s) pour cette référence ${quantityError.reference} – ${quantityError.commande}.`
            : undefined;
}


function getQuantityErrorModalNewLigneReception() {
    let $modalNewLigneReception = $("#modalNewLigneReception");
    const conditionnementArticleArray$ = $modalNewLigneReception.find('.articles-conditionnement-container .conditionnement-article');
    const quantityByConditionnementArray = [];

    conditionnementArticleArray$.each(function () {
        const $conditionnement = $(this);

        const referenceConditionnement = $conditionnement.find('input[name="refArticle"]').val();
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

function initWithPH(select, ph, ajax = true, route = null) {
    if (ajax) {
        select.select2({
            ajax: {
                url: route,
                dataType: 'json',
                delay: 250,
            },
            language: {
                inputTooShort: function () {
                    return 'Veuillez entrer au moins 1 caractère.';
                },
                searching: function () {
                    return 'Recherche en cours...';
                },
                noResults: function () {
                    return 'Aucun résultat.';
                }
            },
            minimumInputLength: 1,
            placeholder: ph
        });
    } else {
        select.select2({
            placeholder: ph
        });
    }
}

function removePackingItem($button) {
    $button.closest('.conditionnement-article').remove();
}


function displayErrorRA(data, modal) {
    if (data.success === true) {
        modal.parent().html('');
    } else {
        modal.find('.error-msg').html(data.msg);
    }
}

function printBarcode(button) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let params = {
        'reception': button.data('id')
    };
    $.post(Routing.generate('get_article_refs'), JSON.stringify(params), function (response) {
        if (response.exists) {
            if (response.refs.length > 0) {
                printBarcodes(response.refs, response, 'Etiquettes du ' + date + '.pdf', response.barcodeLabel);
            } else {
                alertErrorMsg('Il n\'y a aucune étiquette à imprimer.');
            }
        }
    });
}
