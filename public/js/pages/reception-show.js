let tableArticle;
let tableLitigesReception;
let modalNewLigneReception = "#modalNewLigneReception";
let $modalNewLigneReception = $(modalNewLigneReception);
let modalArticleAlreadyInit = false;
let tableArticleLitige;

$(function () {
    $('.select2').select2();
    const dataTableInitRes = InitPageDataTable();
    tableArticle = dataTableInitRes.tableArticle;
    tableLitigesReception = dataTableInitRes.tableLitigesReception;
    initPageModals();
    $('#packing-package-number, #packing-number-in-package').on('keypress keydown keyup', function () {
        if ($(this).val() === '' || $(this).val() < 0) {
            $(this).val('');
        }
    });

    $('#modalNewLitige').on('change', 'select[name=disputePacks]', function () {
        const data = $(this).select2('data');
        const isUrgent = data.some((article) => article.isUrgent);
        $(this).parents('.modal').first().find('input[name=emergency]').prop('checked', isUrgent);
    });

    const $modalNewLigneReception = $(`#modalNewLigneReception`);

    const $refArticleCommande = $modalNewLigneReception.find(`select[name=refArticleCommande]`);
    $refArticleCommande
        .on(`change`, function () {
            initConditionnementArticleFournisseurDefault();
            if($(this).select2(`data`).length === 0) {
                hidePackingContent($(this));
            }
        });

    $modalNewLigneReception.find(`select[name=articleFournisseurDefault]`)
        .on(`change`, function () {
            const data = $refArticleCommande.select2(`data`);
            if (data.length > 0 && $(this).select2(`data`).length > 0) {
                const {reference, commande} = data[0];
                const supplierReference = $(this).val();
                AJAX.route(`GET`, `packing_template`, {
                    reference,
                    orderNumber: commande,
                    supplierReference
                })
                    .json()
                    .then(({template}) => {
                        $(`.packing-container`).empty().html(template);
                    });
            } else {
                hidePackingContent($(this), false);
            }
        });

    $(document).on(`click`, `.add-articles`, function (e) {
        e.preventDefault();
        const data = Form.process($(`.packing-container`));

        if (data) {
            const params = JSON.stringify(data.asObject());
            wrapLoadingOnActionButton($(this), () => (
                AJAX.route(`GET`, `add_articles`, {params})
                    .json()
                    .then(({template, values}) => {
                        const $articlesContainer = $(`.articles-container`);
                        const $modal = $articlesContainer.closest(`.modal`);
                        $articlesContainer.append(template);
                        $modal.find(`.wii-section-title, .create-request-container, .modal-footer`).removeClass(`d-none`);
                        const packingArticlesValue = $modal.find(`input[name=packingArticles]`).val();

                        const currentArticles = packingArticlesValue ? JSON.parse(packingArticlesValue) : [];
                        const {
                            supplierReferenceId,
                            batch,
                            expiry,
                            quantity,
                            referenceId,
                            orderNumber,
                            freeFields
                        } = values;

                        currentArticles.push({
                            articleFournisseur: supplierReferenceId,
                            batch: batch,
                            expiry: expiry,
                            noCommande: orderNumber,
                            quantite: quantity,
                            refArticle: referenceId,
                            ...freeFields
                        });
                        $modal.find(`input[name=packingArticles]`).val(JSON.stringify(currentArticles));
                    })
            ));
        }
    });

    $(document).on(`click`, `.remove-article-line`, function () {
        $(this).closest(`.article-line`).remove();
        const $articlesContainer = $(`.articles-container`);
        if($articlesContainer.find('.article-line').length === 0) {
            $articlesContainer.closest(`.modal`)
                .find(`.wii-section-title, .create-request-container, .modal-footer`)
                .addClass(`d-none`);
        }
        Flash.add(`success`, `Les articles générés ont bien été supprimés.`);
    });

    $(`[name=requestType]`).on(`change`, function () {
        const type = $(this).val();

        switch (type) {
            case 'transfer':
                toggleForm($('.transfer-form'), $(this));
                break;
            case 'delivery':
                toggleForm($('.demande-form'), $(this));
                break;
            default:
                toggleForm($('.transfer-form, .demande-form'), null);
                break;
        }
    });
});

function initPageModals() {
    let $modalAddLigneArticle = $("#modalAddLigneArticle");
    let $submitAddLigneArticle = $("#addArticleLigneSubmit");
    let $submitAndRedirectLigneArticle = $('#addArticleLigneSubmitAndRedirect');
    let urlAddLigneArticle = Routing.generate('reception_article_add', true);
    InitModal($modalAddLigneArticle, $submitAddLigneArticle, urlAddLigneArticle, {tables: [tableArticle]});
    InitModal($modalAddLigneArticle, $submitAndRedirectLigneArticle, urlAddLigneArticle, {
        tables: [tableArticle],
        success: createHandlerAddLigneArticleResponseAndRedirect($modalAddLigneArticle),
        keepForm: true,
        keepModal: true
    });

    let $modalDeleteArticle = $("#modalDeleteLigneArticle");
    let $submitDeleteArticle = $("#submitDeleteLigneArticle");
    let urlDeleteArticle = Routing.generate('reception_article_remove', true);
    InitModal($modalDeleteArticle, $submitDeleteArticle, urlDeleteArticle, {tables: [tableArticle]});

    let $modalEditArticle = $("#modalEditLigneArticle");
    let $submitEditArticle = $("#submitEditLigneArticle");
    let urlEditArticle = Routing.generate('reception_article_edit', true);
    InitModal($modalEditArticle, $submitEditArticle, urlEditArticle, {tables: [tableArticle]});

    let $modalDelete = $("#modalDeleteReception");
    let $submitDelete = $("#submitDeleteReception");
    let urlDeleteReception = Routing.generate('reception_delete', true);
    InitModal($modalDelete, $submitDelete, urlDeleteReception);

    let $modalCancel = $("#modalCancelReception");
    let $submitCancel = $("#submitCancelReception");
    let urlCancelReception = Routing.generate('reception_cancel', true);
    InitModal($modalCancel, $submitCancel, urlCancelReception);

    let $modalModifyReception = $('#modalEditReception');
    let $submitModifyReception = $('#submitEditReception');
    let urlModifyReception = Routing.generate('reception_edit', true);
    InitModal($modalModifyReception, $submitModifyReception, urlModifyReception);

    let modalNewLitige = $('#modalNewLitige');
    let submitNewLitige = $('#submitNewLitige');
    let urlNewLitige = Routing.generate('dispute_new_reception', true);
    InitModal(modalNewLitige, submitNewLitige, urlNewLitige, {tables: [tableLitigesReception]});

    let modalEditLitige = $('#modalEditLitige');
    let submitEditLitige = $('#submitEditLitige');
    let urlEditLitige = Routing.generate('litige_edit_reception', true);
    InitModal(modalEditLitige, submitEditLitige, urlEditLitige, {tables: [tableLitigesReception]});

    let $modalDeleteLitige = $("#modalDeleteLitige");
    let $submitDeleteLitige = $("#submitDeleteLitige");
    let urlDeleteLitige = Routing.generate('litige_delete_reception', true);
    InitModal($modalDeleteLitige, $submitDeleteLitige, urlDeleteLitige, {tables: [tableLitigesReception]});
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
        order: [['Urgence', "desc"], ['Référence', "desc"]],
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
            {"data": 'lastHistoryRecord', 'name': 'lastHistoryRecord', 'title': 'Dernier historique'},
            {"data": 'date', 'name': 'date', 'title': 'Date', visible: false},
            {"data": 'urgence', 'name': 'urgence', 'title': 'urgence', visible: false},
        ],
        order: [
            ['urgence', 'desc'],
            ['date', 'desc'],
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

    Select2Old.provider($('.ajax-autocomplete-fournisseur-edit'));
    Select2Old.location($('.ajax-autocomplete-location-edit'));
    Select2Old.carrier($('.ajax-autocomplete-transporteur-edit'));
}

function initDateTimePickerReception() {
    initDateTimePicker('#dateCommande, #dateAttendue');
    $('.date-cl').each(function () {
        initDateTimePicker('#' + $(this).attr('id'));
    });
}

function editRowLitigeReception(button, afterLoadingEditModal = () => {}, receptionId, disputeId, disputeNumber) {
    let path = Routing.generate('litige_api_edit_reception', true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');

    let params = {
        disputeId,
        reception: receptionId,
        disputeNumber: disputeNumber
    };

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        Select2Old.articleReception(modal.find('.select2-autocomplete-articles'));
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

    modal.find(submit).attr('value', disputeId);
    $('#disputeNumberReception').text(disputeNumber);
}

function getCommentAndAddHisto() {
    let path = Routing.generate('add_comment', {dispute: $('#disputeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function (response) {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}

function openTableHisto() {
    let pathHistoLitige = Routing.generate('histo_dispute_api', {dispute: $('#disputeId').val()}, true);
    let tableHistoLitigeConfig = {
        ajax: {
            "url": pathHistoLitige,
            "type": "POST"
        },
        columns: [
            {data: 'user', name: 'Utilisateur', title: 'Utilisateur'},
            {data: 'date', name: 'date', title: 'Date'},
            {data: 'commentaire', name: 'commentaire', title: 'Commentaire'},
            {data: 'status', name: 'status', title: 'Statut'},
            {data: 'type', name: 'type', title: 'Type'},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        domConfig: {
            needsPartialDomOverride: true
        },
    };
    tableHistoLitige = initDataTable('tableHistoLitige', tableHistoLitigeConfig);
    tableArticleLitige = initTableArticleLitige();
}

function initDatatableConditionnement() {
    let pathArticle = Routing.generate('article_by_reception_api', true);
    let tableFromArticleConfig = {
        info: false,
        paging: false,
        searching: false,
        destroy: true,
        processing: true,
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
        order: [['barCode', 'asc']],
        columns: [
            {"data": 'actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'barCode', 'name': 'barCode', 'title': 'Code article'},
            {"data": "status", 'name': 'status', 'title': 'Statut'},
            {"data": 'label', 'name': 'label', 'title': 'Libellé'},
            {"data": 'articleReference', 'name': 'articleReference', 'title': 'Référence article'},
            {"data": 'quantity', 'name': 'quantity', 'title': 'Quantité'},
        ],
        aoColumnDefs: [{
            'sType': 'natural',
            'bSortable': true,
            'aTargets': [1]
        }]
    };
    let tableFromArticle = initDataTable('tableArticleInner_id', tableFromArticleConfig);

    let statutVisible = $("#statutVisible").val();
    if (!statutVisible) {
        tableFromArticle.column('Statut:name').visible(false);
    }
    if (!modalArticleAlreadyInit) {
        initModalCondit(tableFromArticle);
        modalArticleAlreadyInit = true;
    }
}

function initModalCondit(tableFromArticle) {
    let $modalEditInnerArticle = $("#modalEditArticle");
    let $submitEditInnerArticle = $("#submitEditArticle");
    let urlEditInnerArticle = Routing.generate('article_edit', true);
    InitModal($modalEditInnerArticle, $submitEditInnerArticle, urlEditInnerArticle, {tables: [tableFromArticle]});

    let $modalDeleteInnerArticle = $("#modalDeleteArticle");
    let $submitDeleteInnerArticle = $("#submitDeleteArticle");
    let urlDeleteInnerArticle = Routing.generate('article_delete', true);
    InitModal($modalDeleteInnerArticle, $submitDeleteInnerArticle, urlDeleteInnerArticle, {tables: [tableFromArticle, tableArticle]});
}

function initNewArticleEditor(modal) {
    const $modal = $(modal);
    let $select2refs = $modal.find('[name="referenceArticle"]');
    Select2Old.articleReference($select2refs);

    clearAddRefModal();
    clearModal(modal);

    const $commandField = $(modal).find('[name="commande"]');
    const $numCommand = $('#numCommandeReception').val();
    $commandField.val($numCommand);

    const $button = $('#addArticleLigneSubmitAndRedirect');
    $button.addClass('d-none');

    let $quantiteRecue =  $('#quantiteRecue');
    let $quantiteAR = $('#quantiteAR');
    $quantiteRecue.prop('disabled', true);
    $quantiteRecue.val(0);
    $quantiteAR.val(0);

    setTimeout(() => {
        Select2Old.open($select2refs);
    }, 400);
}

function openModalArticlesFromLigneArticle(ligneArticleId) {
    $('#ligneSelected').val(ligneArticleId);
    $('#chooseConditionnement').click();
    initDatatableConditionnement();
}

function articleChanged($select) {
    const $modal = $select.parents('.modal');
    const selectedReferences = $select.select2('data');
    const $addArticleAndRedirectSubmit = $('#addArticleLigneSubmitAndRedirect');
    const $addArticleLigneSubmit = $('#addArticleLigneSubmit');
    const classDNone = 'd-none';
    const classDFlex = 'd-flex';

    if (selectedReferences.length > 0) {
        const selectedReference = selectedReferences[0];
        const typeQuantity = selectedReference.typeQuantity;

        $addArticleLigneSubmit.prop('disabled', false);
        if (typeQuantity === 'article') {
            $addArticleAndRedirectSubmit.removeClass(classDNone);
        } else {
            $addArticleAndRedirectSubmit.addClass(classDNone);
        }

        const $emergencyContainer = $('.emergency');
        const $emergencyCommentContainer =  $('.emergency-comment');
        if (selectedReference.urgent) {
            $emergencyContainer.removeClass(classDNone);
            $emergencyCommentContainer.text(selectedReference.emergencyComment);
        } else {
            $emergencyContainer.addClass(classDNone);
            $emergencyCommentContainer.text('');
        }
        $modal.find('.body-add-ref')
            .removeClass(classDNone)
            .addClass(classDFlex);
        $('#innerNewRef').html('');
    }
    else {
        $addArticleAndRedirectSubmit.addClass(classDNone);
        $addArticleLigneSubmit.prop('disabled', true);
        $modal.find('.body-add-ref')
            .addClass(classDNone)
            .removeClass(classDFlex);
    }
}

function initNewReferenceArticleEditor($modal) {
    Select2Old.provider($modal.find('.ajax-autocomplete-fournisseur'));
    Select2Old.provider($modal.find('.ajax-autocomplete-fournisseurLabel'), '', 'demande_label_by_fournisseur');
    Select2Old.location($modal.find('.ajax-autocomplete-location'));
    let modalRefArticleNew = $("#new-ref-inner-body");
    let submitNewRefArticle = $("#submitNewRefArticleFromRecep");
    let urlRefArticleNew = Routing.generate('reference_article_new', true);
    InitModal(modalRefArticleNew, submitNewRefArticle, urlRefArticleNew, {
        keepModal: true,
        success: ({success, data}) => {
            if (success && data) {
                let option = new Option(data.reference, data.id, true, true);
                $('#reception-add-ligne').append(option).trigger('change');
            }
        }
    });
}

function addArticle() {
    let path = Routing.generate('get_modal_new_ref', true);
    $.post(path, {}, function (modalNewRef) {
        $('#reception-add-ligne').val(null).trigger('change');
        const $modal = $('#innerNewRef');
        $modal.html(modalNewRef);
        initNewReferenceArticleEditor($modal);
    });
}

function finishReception(receptionId, confirmed, $button) {
    wrapLoadingOnActionButton($button, () => (
        $.post(Routing.generate('reception_finish'), JSON.stringify({
            id: receptionId,
            confirmed: confirmed
        }), function (data) {
            const code = data.code;
            if (code === 1) {
                window.location.href = data.redirect;
            } else if (code === 0) {
                $('#finishReception').click();
            } else {
                showBSAlert(data, 'danger');
            }
        }, 'json')
    ), true);
}

function clearAddRefModal() {
    $('#innerNewRef').html('');
    $('.body-add-ref').addClass('d-none');
}

function afterLoadingEditModal($button) {
    initRequiredChampsFixes($button);
}

function openModalLigneReception($button) {
    clearModalLigneReception('#modalNewLigneReception');
    initNewLigneReception($button);
}

function clearModalLigneReception(modal) {
    const $modal = $(modal);

    $modal
        .find(".transfer-form")
        .addClass("d-none");

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
    toggleForm($('.transfer-form, .demande-form'), null, true);
    $modal.find(`.packing-container, .articles-container`).empty();
    $modal.find(`.wii-section-title, .create-request-container, .modal-footer`).addClass(`d-none`);
    $modal.find(`select[name=articleFournisseurDefault]`).closest(`.form-group`).addClass(`d-none`);
}

function demandeurChanged($select) {
    const $container = $select.closest('.demande-form');
    const $locationSelect = $container.find('[name="destination"]');
    const [resultSelected] = $select.select2('data');

    if (resultSelected) {
        let {locationId, locationLabel} = resultSelected;

        if (locationId && locationLabel && locationId.indexOf('location:') === 0) {
            locationId = locationId.split(":").pop();
            locationLabel = locationLabel.split(":").pop();
            const $value = $('<div/>');
            $value.data('id', locationId)
            $value.data('text', locationLabel)
            Select2Old.initValues($locationSelect, $value, true);
        }
    }
}

function initNewLigneReception($button) {
    const restrictedLocations = $modalNewLigneReception.find(`input[name=restrictedLocations]`).val();
    Select2Old.init($modalNewLigneReception.find('.ajax-autocomplete-location'), '', restrictedLocations ? 0 : 1, {route: 'get_emplacement'});
    Select2Old.location($('.ajax-autocomplete-location-edit'));
    Select2Old.init($('.select2-type'));
    Select2Old.user($modalNewLigneReception.find('.select2-user'));
    Select2Old.initValues($('select[name=demandeur]'), $( '#currentUser'));
    Select2Old.init($modalNewLigneReception.find('.select2-autocomplete-ref-articles'), '', 0, {
        route: 'get_ref_article_reception',
        param: {
            reception: $('#receptionId').val()
        }
    });

    if ($('#locationDemandeLivraison').length > 0) {
        Select2Old.initValues($('#locationDemandeLivraison'), $('#locationDemandeLivraisonValue'));
    }

    if ($('#storageTransfer').length > 0) {
        Select2Old.initValues($('#storage'), $('#storageTransfer'));
    }

    if ($('#originTransfer').length > 0) {
        Select2Old.initValues($('#origin'), $('#originTransfer'));
    }

    let urlNewLigneReception = Routing.generate(
        'reception_new_with_packing',
        {reception: $modalNewLigneReception.find('input[type="hidden"][name="reception"]').val()},
        true
    );
    let $submitNewReceptionButton = $modalNewLigneReception.find("#submitNewReceptionButton");

    $submitNewReceptionButton.off('click');
    $submitNewReceptionButton.click(function () {
        wrapLoadingOnActionButton($button, () => (
            SubmitAction($modalNewLigneReception, $submitNewReceptionButton, urlNewLigneReception, {
                tables: [tableArticle],
                success: (response) => {
                    if (response && response.success) {
                        const $printButton = $('#buttonPrintMultipleBarcodes');
                        if ($printButton.length > 0) {
                            window.location.href = $printButton.attr('href');
                        }
                    }
                },
                keepForm: true
            })
        ));
    });

    const $select = $modalNewLigneReception.find('.demande-form [name="type"]');
    toggleRequiredChampsLibres($select, 'create');
    typeChoice($select);
}

function onRequestTypeChange($select) {
    const $freeFieldContainer = $modalNewLigneReception.find('.demande-form .free-fields-container');
    toggleRequiredChampsLibres($select, 'create', $freeFieldContainer);
    typeChoice($select, $freeFieldContainer);
    toggleLocationSelect($select, $select.closest('.demande-form'));
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
        if (!data.success) {
            if (data.msg) {
                showBSAlert(data.msg, 'danger');
            }
        } else {
            $modal.find('.close').click();
            clearModal($modal);
        }
    }
}

function createHandlerAddLigneArticleResponseAndRedirect($modal) {
    return (data) => {
        const [{text: refSelectedReference} = {}] = $modal.find('select[name="referenceArticle"]').select2('data') || [];
        const commande = $modal.find('input[name="commande"]').val();
        createHandlerAddLigneArticleResponse($modal)(data);
        if (data.success) {
            $('#modalNewLigneReception').modal('show');

            $.get({
                url: Routing.generate('get_ref_article_reception', {
                    reception: $('#receptionId').val(),
                    reference: refSelectedReference,
                    commande
                })
            })
            .then(({results}) => {
                if (results && results.length > 0) {
                    const [selected] = results;
                    if (selected) {
                        const $pickingSelect = $('#modalNewLigneReception').find('#referenceConditionnement');
                        let newOption = new Option(selected.text, selected.id, true, true);
                        $pickingSelect
                            .append(newOption)
                            .val(selected.id);
                        const [selectedPicking] = $pickingSelect.select2('data');
                        Object.assign(selectedPicking, selected);
                        $pickingSelect.trigger(`change`);
                    }
                }
            });
        }
    }
}

function updateQuantityToReceive($input) {
    $input.closest('.modal').find('[name="quantite"]').attr('max', $input.val());
}

function toggleForm($content, $input, force = false) {
    if (force) {
        $content = $('.transfer-form');
        $content.addClass('d-none');
        $content.find('.data').attr('disabled', 'disabled');
        if ($('input[name="create-demande"]').is(':checked')) {
            $('.demande-form').removeClass('d-none');
            $('.demande-form').find('.data').prop('disabled', false);
        }
    } else {
        if ($input && $input.is(':checked')) {
            $content.removeClass('d-none');
            $content.find('.data').prop('disabled', false);

            if ($content.hasClass('transfer-form')) {
                $('.demande-form').addClass('d-none');
                $('.demande-form').find('.data').attr('disabled', 'disabled');
                $('.demande-form').find('.wii-switch').removeClass('needed');
                $('input[name="create-demande"]').prop('checked', false);
            } else {
                $('.transfer-form').addClass('d-none');
                $('.transfer-form').find('.data').attr('disabled', 'disabled');
                $('input[name="create-demande-transfert"]').prop('checked', false);
                $('.demande-form select').addClass('needed')

            }
        } else {
            $content.addClass('d-none');
            $content.find('.data').prop('disabled', true);
        }
    }
}

function initConditionnementArticleFournisseurDefault() {
    const $selectRefArticle = $('#modalNewLigneReception select[name="refArticleCommande"]');
    const [referenceArticle] = $selectRefArticle.select2('data');
    const $selectArticleFournisseur = $('#modalNewLigneReception select[name="articleFournisseurDefault"]');

    if (referenceArticle) {
        const {reference, defaultArticleFournisseur} = referenceArticle;
        resetDefaultArticleFournisseur(true);
        Select2Old.init(
            $selectArticleFournisseur,
            '',
            1,
            {
                route: 'get_article_fournisseur_autocomplete',
                param: {
                    referenceArticle: reference
                }
            },
            {},
            defaultArticleFournisseur || {}
        );
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

    $selectArticleFournisseur.val(null).trigger(SELECT2_TRIGGER_CHANGE);

    if (show) {
        $selectArticleFournisseurFormGroup.removeClass('d-none');
    } else {
        $selectArticleFournisseurFormGroup.addClass('d-none');
    }
}

function initRequiredChampsFixes(button) {
    let params = {id: button.data('id')};
    let path = Routing.generate('get_quantity_type');

    $.post(path, JSON.stringify(params), function (data) {
        displayRequiredChampsFixesByTypeQuantiteReferenceArticle(data, button)
    }, 'json');
}

function hidePackingContent($select, hideSupplierReferenceSelect = true) {
    const $modal = $select.closest(`.modal`);
    $modal.find(`.packing-container, .articles-container`).empty();
    $modal.find(`.wii-section-title, .create-request-container, .modal-footer`).addClass(`d-none`);
    if(hideSupplierReferenceSelect) {
        $modal.find(`select[name=articleFournisseurDefault]`).closest(`.form-group`).addClass(`d-none`);
    }
}
