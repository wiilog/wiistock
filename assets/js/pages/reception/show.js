import '@styles/pages/reception/show.scss';

import Routing from '../../../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
import AJAX, {GET, POST} from "@app/ajax";
import Select2 from "@app/select2";
import Modal from "@app/modal";
import Flash, {ERROR} from "@app/flash";

let modalNewLigneReception = "#modalNewLigneReception";
let $modalNewLigneReception = $(modalNewLigneReception);
let modalArticleAlreadyInit = false;
let tableArticleLitige;
let tableHistoLitige;
let receptionDisputesDatatable;
let articleSearch;

window.initNewArticleEditor = initNewArticleEditor;
window.openModalNewReceptionReferenceArticle = openModalNewReceptionReferenceArticle;
window.finishReception = finishReception;
window.onRequestTypeChange = onRequestTypeChange;
window.demandeurChanged = demandeurChanged;
window.addArticle = addArticle;
window.articleChanged = articleChanged;
window.openModalArticlesFromLigneArticle = openModalArticlesFromLigneArticle;
window.openTableHisto = openTableHisto;
window.getCommentAndAddHisto = getCommentAndAddHisto;
window.editRowLitigeReception = editRowLitigeReception;
window.initEditReception = initEditReception;

$(function () {
    $('.select2').select2();
    receptionDisputesDatatable = InitDisputeDataTable();
    initPageModals();
    launchPackListSearching();
    loadReceptionLines();

    $('#modalNewLitige').on('change', 'select[name=disputePacks]', function () {
        const data = $(this).select2('data');
        const isUrgent = data.some((article) => article.isUrgent);
        $(this).parents('.modal').first().find('input[name=emergency]').prop('checked', isUrgent);
    });

    const $modalNewLigneReception = $(`#modalNewLigneReception`);

    const $referenceToReceive = $modalNewLigneReception.find(`select[name=referenceToReceive]`);
    $referenceToReceive.on(`change`, function () {
        onReferenceToReceiveChange();
    });

    $modalNewLigneReception.find('select[name=articleFournisseurDefault], select[name=pack]')
        .on(`change`, function () {
            loadPackingArticleForm($modalNewLigneReception);
        });

    $(document).on(`click`, `.add-packing-lines`, function (e) {
        e.preventDefault();
        loadPackingArticlesTemplate($(this));
    });

    $(document).on(`click`, `.remove-article-line`, function () {
        const $currentArticleLine = $(this).closest(`.article-line`);
        const $modal = $currentArticleLine.closest(`.modal`);
        const $articlesContainer = $currentArticleLine.closest(`.articles-container`);

        $currentArticleLine.remove();
        if($articlesContainer.find(`.article-line`).length === 0) {
            clearPackingContent($modal, false, false);
        }

        Flash.add(`success`, `La ligne de conditionnement a bien été supprimée.`);
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

function initPageModals() {
    let $modalNewReceptionReferenceArticle = $("#modalNewReceptionReferenceArticle");
    let $submitAddLigneArticle = $modalNewReceptionReferenceArticle.find("[type=submit]");
    let $submitAndRedirectLigneArticle = $('#addArticleLigneSubmitAndRedirect');
    let urlAddLigneArticle = Routing.generate('reception_reference_article_new', true);
    InitModal($modalNewReceptionReferenceArticle, $submitAddLigneArticle, urlAddLigneArticle, {
        success: () => {
            loadReceptionLines();
        }
    });
    InitModal($modalNewReceptionReferenceArticle, $submitAndRedirectLigneArticle, urlAddLigneArticle, {
        success: createHandlerAddLigneArticleResponseAndRedirect($modalNewReceptionReferenceArticle),
        keepForm: true,
        keepModal: true
    });

    $modalNewReceptionReferenceArticle.on(`show.bs.modal`, function() {
        const {label, reference, is_article} = GetRequestQuery();
        const $select = $(this).find(`[name="referenceArticle"]`);

        if(label && reference) {
            $select.append(new Option(label, reference, true, true));
            $select.trigger(`change`);
            if(is_article === '1'){
                $modalNewReceptionReferenceArticle.find(`#addArticleLigneSubmitAndRedirect`).removeClass(`d-none`);
            }

            setTimeout(() => SetRequestQuery({}), 1);
        }
        Select2Old.articleReference($select);
    });

    let $modalDeleteReceptionReferenceArticle = $("#modalDeleteReceptionReferenceArticle");
    let $submitDeleteReceptionReferenceArticle = $("#submitDeleteReceptionReferenceArticle");
    let urlReceptionReferenceArticle = Routing.generate('reception_reference_article_remove', true);
    InitModal($modalDeleteReceptionReferenceArticle, $submitDeleteReceptionReferenceArticle, urlReceptionReferenceArticle, {
        success: () => {
            loadReceptionLines();
        }
    });

    let $modalEditArticle = $("#modalEditReceptionReferenceArticle");
    let $submitEditArticle = $("#submitEditLigneArticle");
    let urlEditArticle = Routing.generate('reception_reference_article_edit', true);
    InitModal($modalEditArticle, $submitEditArticle, urlEditArticle, {
        success: () => {
            loadReceptionLines();
        }
    });

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
    InitModal(modalNewLitige, submitNewLitige, urlNewLitige, {tables: [receptionDisputesDatatable]});

    let modalEditLitige = $('#modalEditLitige');
    let submitEditLitige = $('#submitEditLitige');
    let urlEditLitige = Routing.generate('litige_edit_reception', true);
    InitModal(modalEditLitige, submitEditLitige, urlEditLitige, {tables: [receptionDisputesDatatable]});

    let $modalDeleteLitige = $("#modalDeleteLitige");
    let $submitDeleteLitige = $("#submitDeleteLitige");
    let urlDeleteLitige = Routing.generate('litige_delete_reception', true);
    InitModal($modalDeleteLitige, $submitDeleteLitige, urlDeleteLitige, {tables: [receptionDisputesDatatable]});
}

function InitDisputeDataTable() {
    let pathLitigesReception = Routing.generate('litige_reception_api', {reception: $('#receptionId').val()}, true);

    let tableLitigeConfig = {
        lengthMenu: [5, 10, 25],
        ajax: {
            url: pathLitigesReception,
            type: "POST",
        },
        columns: [
            {data: 'actions', name: 'Actions', title: '', className: 'noVis', orderable: false},
            {data: 'type', name: 'type', title: 'Type'},
            {data: 'status', name: 'status', title: 'Statut'},
            {data: 'lastHistoryRecord', name: 'lastHistoryRecord', title: 'Dernier historique'},
            {data: 'date', name: 'date', title: 'Date', visible: false},
            {data: 'urgence', name: 'urgence', title: 'urgence', visible: false},
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
    return initDataTable('tableReceptionLitiges', tableLitigeConfig);
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
            url: pathHistoLitige,
            type: "POST"
        },
        columns: [
            {data: 'user', name: 'Utilisateur', title: Translation.of('Traçabilité', 'Général', 'Utilisateur')},
            {data: 'date', name: 'date', title: Translation.of('Traçabilité', 'Général', 'Date')},
            {data: 'commentaire', name: 'commentaire', title: Translation.of('Général', '', 'Modale', 'Commentaire')},
            {data: 'status', name: 'status', title: Translation.of('Qualité', 'Litiges', 'Statut')},
            {data: 'type', name: 'type', title: Translation.of('Qualité', 'Litiges', 'Type')},
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
            url: pathArticle,
            type: "POST",
            data: function () {
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
            {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
            {data: 'barCode', name: 'barCode', title: 'Code article'},
            {data: "status", name: 'status', title: 'Statut'},
            {data: 'label', name: 'label', title: 'Libellé'},
            {data: 'articleReference', name: 'articleReference', title: 'Référence article'},
            {data: 'quantity', name: 'quantity', title: 'Quantité'},
        ],
        aoColumnDefs: [{
            sType: 'natural',
            bSortable: true,
            aTargets: [1]
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
    InitModal($modalDeleteInnerArticle, $submitDeleteInnerArticle, urlDeleteInnerArticle, {
        tables: [tableFromArticle],
        success: () => {
            loadReceptionLines();
        }
    });
}

function initNewArticleEditor(modal, options = {}) {
    const $modal = $(modal);
    let $select2refs = $modal.find('[name="referenceArticle"]');

    Select2.destroy($select2refs);
    Select2Old.articleReference($select2refs);

    clearModal(modal);

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

    if (options['unitCode'] && options['unitId']) {
        let $selectUl = $modal.find('[name="pack"]');
        $selectUl.append(new Option(options['unitCode'], options['unitId'], true, true)).trigger('change');
    }
}


function openModalArticlesFromLigneArticle(ligneArticleId) {
    $('#ligneSelected').val(ligneArticleId);
    $('#chooseConditionnement').click();
    initDatatableConditionnement();
}

function articleChanged($select) {
    const $modal = $select.closest(`.modal`);
    if(!$select.data(`select2`)) {
        return;
    }

    const selectedReference = $select.select2(`data`);
    const $addArticleAndRedirectSubmit = $(`#addArticleLigneSubmitAndRedirect`);
    let $modalNewReceptionReferenceArticle = $("#modalNewReceptionReferenceArticle");
    let $addArticleLigneSubmit = $modalNewReceptionReferenceArticle.find("[type=submit]");

    if (selectedReference.length > 0) {
        const {typeQuantity, urgent, emergencyComment} = selectedReference[0];

        $addArticleLigneSubmit.prop(`disabled`, false);
        $addArticleAndRedirectSubmit.toggleClass(`d-none`, typeQuantity !== `article`)

        const $emergencyContainer = $(`.emergency`);
        const $emergencyCommentContainer =  $(`.emergency-comment`);
        if (urgent) {
            $emergencyContainer.removeClass(`d-none`);
            $emergencyCommentContainer.text(emergencyComment);
        } else {
            $emergencyContainer.addClass(`d-none`);
            $emergencyCommentContainer.text(``);
        }
        $modal.find(`.body-add-ref`)
            .removeClass(`d-none`)
            .addClass(`d-flex`);
        $('#innerNewRef').html(``);
    }
    else {
        $addArticleAndRedirectSubmit.addClass(`d-none`);
        $addArticleLigneSubmit.prop(`disabled`, true);
        $modal.find(`.body-add-ref`)
            .addClass(`d-none`)
            .removeClass(`d-flex`);
    }
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

function openModalNewReceptionReferenceArticle() {
    clearModalLigneReception('#modalNewLigneReception');
    initNewLigneReception();
}

function clearModalLigneReception(modal) {
    const $modal = $(modal);
    clearModal($modal);

    $modal
        .find(".transfer-form")
        .addClass("d-none");

    $modal
        .find('.articles-conditionnement-container')
        .html('');

    const $select2 = $modal.find('select[name="referenceToReceive"]');
    if ($select2.hasClass("select2-hidden-accessible")) {
        $select2
            .val(null)
            .html('');
        $select2.select2('data', null);
        $select2.select2('destroy');
    }

    toggleForm($('.transfer-form, .demande-form'), null, true);
    clearPackingContent($modal);
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

function initNewLigneReception() {
    const restrictedLocations = $modalNewLigneReception.find(`input[name=restrictedLocations]`).val();
    Select2Old.init($modalNewLigneReception.find('.ajax-autocomplete-location'), '', restrictedLocations ? 0 : 1, {route: 'get_emplacement'});
    Select2Old.location($('.ajax-autocomplete-location-edit'));
    Select2Old.init($('.select2-type'));
    Select2Old.user($modalNewLigneReception.find('.select2-user'));
    Select2Old.initValues($('select[name=demandeur]'), $( '#currentUser'));
    Select2Old.init($modalNewLigneReception.find('[name=referenceToReceive]'), '', 0, {
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

    Form.create($modalNewLigneReception)
        .onSubmit((data, form) => {
            const reception = form.element.find('input[name="reception"]').val();
            const $articleLines = form.element.find(`.article-line`);
            const packingArticlesValues = $articleLines
                .map((index, line) => $(line).data('value'))
                .toArray();
            const showLocationAlertMessage = $articleLines
                .map((index, line) => $(line).data('drop-location-is-reception-location'))
                .toArray()
                .some((dropLocationIsReceptionLocation) => dropLocationIsReceptionLocation === 0);

            data.append('packingArticles', JSON.stringify(packingArticlesValues))

            if (showLocationAlertMessage) {
                Modal.confirm({
                    message: `
                        <p class="mb-2 text-center">L'emplacement de réception est différent de l'emplacement de l'unité logistique.</p>
                        <p class="text-center">Les articles seront déposés sur l'emplacement de réception puis déplacés sur l'emplacement de l'unité logistique.</p>
                    `,
                    validateButton: {
                        color: 'success',
                        label: 'Continuer',
                        click: () => {
                            form.loading(() => submitPackingForm({reception, data, $modalNewLigneReception}));
                        }
                    },
                });
            }
            else {
                form.loading(() => submitPackingForm({reception, data, $modalNewLigneReception}));
            }
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
        loadReceptionLines();
        createHandlerAddLigneArticleResponse($modal)(data);
        if (data.success) {
            const {receptionReferenceArticle} = data;
            $('#modalNewLigneReception').modal('show');

            if (receptionReferenceArticle) {
                const $pickingSelect = $('#modalNewLigneReception').find('[name=referenceToReceive]');
                let newOption = new Option(receptionReferenceArticle.text, receptionReferenceArticle.id, true, true);
                $pickingSelect
                    .append(newOption)
                    .val(receptionReferenceArticle.id);
                const [selectedPicking] = $pickingSelect.select2('data');
                Object.assign(selectedPicking, receptionReferenceArticle);
                $pickingSelect.trigger(`change`);
            }

            loadReceptionLines();
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
                $('.demande-header-form select').addClass('needed')

            }
        } else {
            $content.addClass('d-none');
            $content.find('.data').prop('disabled', true);
        }
    }
}

function onReferenceToReceiveChange() {
    const $modal = $('#modalNewLigneReception');
    const $firstStepForm = $modal.find('.reference-container');
    const $referenceToReceive = $firstStepForm.find('[name="referenceToReceive"]');
    const [referenceToReceive] = $referenceToReceive.select2('data');

    const $selectArticleFournisseur = $firstStepForm.find('[name=articleFournisseurDefault]');
    const $selectArticleFournisseurFormGroup = $selectArticleFournisseur.closest('.form-group');

    const $selectPack = $firstStepForm.find('[name=pack]');
    const $selectPackFormGroup = $selectPack.closest('.form-group');

    if (referenceToReceive) {
        let {reference, orderNumber, pack, defaultArticleFournisseur} = referenceToReceive;

        $selectPack
            .data('other-params-reference', reference)
            .attr('data-other-params-reference', reference)
            .data('other-params-order-number', orderNumber)
            .attr('data-other-params-order-number', orderNumber);

        // remove old options
        Select2.reload($selectPack)

        // if the reference is only in the reception without a pack
        // => pack == null
        // else if there are multiple pack associated pack === undefined
        if (pack !== undefined) {
            pack = (pack || {});
            $selectPack
                .prop('disabled', true)
                .append(new Option(pack.code || "&nbsp;", pack.id || `-1`, true, true));

            // user can't make a transfer if the article is in a pack
            if (Object.keys(pack).length) {
                $('.create-request-container').find('input[value=transfer]').prop('disabled', true);
            } else {
                $('.create-request-container').find('input[value=transfer]').prop('disabled', false);
            }
        }
        else {
            $selectPack.prop('disabled', false)
        }

        $selectArticleFournisseur
            .val(null)
            .html('')
            .select2('data', null);
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
        $selectArticleFournisseurFormGroup.removeClass('d-none');
        $selectPackFormGroup.removeClass('d-none');

        $modal
            .find('.packing-container')
            .empty();
    }
    else {
        $selectArticleFournisseurFormGroup.addClass('d-none');
        $selectPackFormGroup.addClass('d-none');


        $selectPack
            .prop('disabled', false)
            .removeData('other-params-reference')
            .removeAttr('data-other-params-reference')
            .removeData('other-params-order-number')
            .removeAttr('data-other-params-order-number')
            .val(null).select2('data', null);
    }

    $selectArticleFournisseur.trigger('change');
    $selectPack.trigger('change');
}

function clearPackingContent($element, hideSubFields = true, hidePackingContainer = true) {
    const $modal = $element.is(`.modal`) ? $element : $element.closest(`.modal`);
    $modal.find(`.articles-container, .error-msg`).empty();
    $modal.find(`.wii-section-title, .create-request-container, .modal-footer, .demande-form, .transfer-form`).addClass(`d-none`);
    if(hideSubFields) {
        $modal.find(`select[name=articleFournisseurDefault]`).closest(`.form-group`).addClass(`d-none`);
        $modal.find(`select[name=pack]`).closest(`.form-group`).addClass(`d-none`);
    }

    if(hidePackingContainer) {
        $modal.find(`.packing-container`).empty();
    }
}

function loadReceptionLines({start, search} = {}) {
    start = start || 0;
    const $logisticUnitsContainer = $('.logistic-units-container');
    const reception = $('#receptionId').val();

    const params = {reception, start};
    if (search) {
        params.search = search;
    }
    else {
        clearPackListSearching();
    }

    wrapLoadingOnActionButton(
        $logisticUnitsContainer,
        () => (
            AJAX.route(GET, 'reception_lines_api', params)
                .json()
                .then(data => {
                    $logisticUnitsContainer.html(data.html);
                    $logisticUnitsContainer.find('.articles-container table')
                        .each(function() {
                            const $table = $(this);
                            initDataTable($table, {
                                serverSide: false,
                                ordering: true,
                                paging: false,
                                searching: false,
                                order: [['emergency', "desc"], ['reference', "desc"]],
                                columns: [
                                    {data: 'actions', className: 'noVis hideOrder', orderable: false},
                                    {data: 'reference', title: 'Référence'},
                                    {data: 'orderNumber', title: 'Commande'},
                                    {data: 'quantityToReceive', title: 'À recevoir'},
                                    {data: 'receivedQuantity', title: 'Reçu'},
                                    {data: 'emergency', visible: false},
                                    {data: 'comment', visible: false},
                                ],
                                domConfig: {
                                    removeInfo: true,
                                    needsPaginationRemoval: true,
                                    removeLength: true,
                                    removeTableHeader: true,
                                },
                                rowConfig: {
                                    needsRowClickAction: true,
                                    needsColor: true,
                                    dataToCheck: 'emergency',
                                    color: 'danger',
                                    callback: (row, data) => {
                                        if (data.emergency && data.comment) {
                                            const $row = $(row);
                                            $row.attr('title', data.comment);
                                            initTooltips($row);
                                        }
                                    }
                                },
                            })
                        });

                    $logisticUnitsContainer
                        .find('.paginate_button:not(.disabled)')
                        .on('click', function() {
                            const $button = $(this);
                            loadReceptionLines({
                                start: $button.data('page'),
                                search: articleSearch
                            });
                        });
                })
        )
    )
}

function launchPackListSearching() {
    const $logisticUnitsContainer = $('.logistic-units-container');
    const $searchInput = $logisticUnitsContainer
        .closest('.content')
        .find('input[type=search]');

    $searchInput.on('input', function () {
        const $input = $(this);
        const articleSearch = $input.val();
        loadReceptionLines({search: articleSearch});
    });
}

function clearPackListSearching() {
    const $logisticUnitsContainer = $('.logistic-units-container');
    const $searchInput = $logisticUnitsContainer
        .closest('.content')
        .find('input[type=search]');
    $searchInput.val(null);
}

function loadPackingArticleForm($modal) {
    const $referenceContainer = $modal.find(`.reference-container`);

    const $referenceToReceive = $referenceContainer.find(`select[name=referenceToReceive]`)
    const $pack = $referenceContainer.find(`[name=pack]`);
    const $supplierArticleDefault = $referenceContainer.find(`[name=articleFournisseurDefault]`);
    const $reception = $referenceContainer.find(`[name=reception]`);

    const [referenceToReceive] = $referenceToReceive.hasClass("select2-hidden-accessible") ? $referenceToReceive.select2(`data`) : [];

    if (referenceToReceive && $supplierArticleDefault.select2(`data`).length > 0) {
        const $packingContainer = $modal.find(`.packing-container`);

        wrapLoadingOnActionButton($packingContainer, () => (
            AJAX.route(GET, `packing_article_form_template`, {
                reception: $reception.val(),
                reference: referenceToReceive.reference,
                orderNumber: referenceToReceive.orderNumber,
                pack: $pack.val(),
                supplierReference: $supplierArticleDefault.val(),
            })
                .json()
                .then(({success, msg, template}) => {
                    if (success) {
                        $packingContainer.html(template);
                    }
                    else {
                        Flash.add(ERROR, msg);
                    }
                })
        ));
    } else {
        clearPackingContent($(this), false);
    }
}

function loadPackingArticlesTemplate($button) {
    const $modal = $button.closest('.modal');
    const $packingContainer = $modal.find('.packing-container');

    if (!($packingContainer.html() || '').trim()) {
        Flash.add(ERROR, 'Veuillez sélectionner une référence et une référence fournisseur.');
        return;
    }
    const data = Form.process($modal.find(`.packing-container`));

    if (data) {
        const params = JSON.stringify(data.asObject());
        wrapLoadingOnActionButton($button, () => (
            AJAX.route(GET, `get_packing_articles_template`, {params})
                .json()
                .then(({template}) => {
                    const $articlesContainer = $modal.find(`.articles-container`);
                    $articlesContainer.append(template);
                    $modal.find(`.wii-section-title, .create-request-container, .modal-footer`).removeClass(`d-none`);

                    if (Number($modal.find(`[name=precheckedDelivery]`).val())) {
                        $modal.find(`.create-request-container`).find(`input[value=delivery]`).trigger(`change`);
                    }
                })
        ));
    }
}

function submitPackingForm({reception, data, $modalNewLigneReception}) {
    return new Promise((resolve) => {
        AJAX
            .route(POST, `reception_new_with_packing`, { reception })
            .json(data)
            .then(({success, articleIds}) => {
                if (success) {
                    let templates;
                    try {
                        templates = JSON.parse($('#tagTemplates').val());
                    } catch (error) {
                        templates = [];
                    }
                    const params = {
                        reception,
                        articleIds
                    };
                    if (templates.length > 0) {
                        Promise.all(
                            [AJAX.route('GET', `reception_bar_codes_print`, {forceTagEmpty: true, ...params}).file({})]
                                .concat(templates.map(function (template) {
                                    params.template = template;
                                    return AJAX
                                        .route('GET', `reception_bar_codes_print`, params)
                                        .file({})
                                }))
                        ).then(() => resolve());
                    } else {
                        window.location.href = Routing.generate('reception_bar_codes_print', {
                            reception,
                            articleIds
                        }, true);
                        resolve();
                    }
                    loadReceptionLines();
                    $modalNewLigneReception.modal('hide');
                }
            });
    });
}
