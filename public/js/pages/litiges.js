$('.select2').select2();
let tableLitiges;
let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit', true);
let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('litige_delete', true);

let tableHistoLitige;
let tableArticleLitige;

$(function () {
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    Select2Old.init($('#carriers'), 'Transporteurs');
    Select2Old.init($('#litigeOrigin'), Translation.of(`Qualité`, `Litiges`, `Origines`));
    Select2Old.user($('.ajax-autocomplete-user:eq(0)'), 'Acheteurs');
    Select2Old.user($('.ajax-autocomplete-user:eq(1)'), 'Déclarant');
    Select2Old.dispute($('.ajax-autocomplete-dispute'), Translation.of(`Qualité`, `Litiges`, `Numéro de litige`));

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_LITIGE_ARR);
    $.post(path, params, function (data) {
        displayFiltersSup(data, true);
    }, 'json');

    initDatatableLitiges();

    InitModal(modalEditLitige, submitEditLitige, urlEditLitige, {tables: [tableLitiges]});
    InitModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, {tables: [tableLitiges]});
});

function initDatatableLitiges() {
    let pathLitiges = Routing.generate('litige_api', true);
    let tableLitigesConfig = {
        serverSide: true,
        processing: true,
        order: [['creationDate', 'desc']],
        ajax: {
            "url": pathLitiges,
            "type": "POST",
        },
        drawConfig: {
            needsSearchOverride: true,
            needsColumnShow: true
        },
        columns: [
            {data: 'actions', title: '', orderable: false, className: 'noVis'},
            {data: 'type', title: 'Type'},
            {data: 'disputeNumber', title: Translation.of(`Qualité`, `Litiges`, `Numéro de litige`)},
            {data: "arrivalNumber", title: Translation.of(`Traçabilité`, `Flux - Arrivages`, `Divers`, `N° d'arrivage`)},
            {data: 'receptionNumber', title: Translation.of(`Traçabilité`, `Association BR`, `N° de réception`)},
            {data: 'buyers', title: Translation.of(`Qualité`, `Litiges`, `Acheteur`), orderable: false},
            {data: 'reporter', title: Translation.of(`Qualité`, `Litiges`, `Déclarant`)},
            {data: 'numCommandeBl', title: Translation.of(`Qualité`, `Litiges`, `N° commande / BL`)},
            {data: 'command', title: 'N° ligne', orderable: false},
            {data: 'provider', title: Translation.of(`Qualité`, `Litiges`, `Fournisseur`)},
            {data: 'references', title: Translation.of(`Traçabilité`, `Mouvements`, `Référence`), orderable: false},
            {data: 'lastHistoryRecord', title: Translation.of(`Qualité`, `Litiges`, `Dernier historique`), orderable: false},
            {data: 'creationDate', title: Translation.of(`Qualité`, `Litiges`, `Créé le`)},
            {data: 'updateDate', title: Translation.of(`Qualité`, `Litiges`, `Modifié le`)},
            {data: 'status', title: 'Statut'},
            {data: 'urgence', title: 'urgence', visible: false, class: 'noVis'},
        ],
        domConfig: {
            needsFullDomOverride: true
        },
        buttons: [
            {
                extend: 'colvis',
                columns: ':not(.noVis)',
                className: 'dt-btn d-none'
            },
        ],
        rowConfig: {
            needsColor: true,
            color: 'danger',
            needsRowClickAction: true,
            dataToCheck: 'urgence'
        },
        page: 'dispute'
    };
    tableLitiges = initDataTable('tableLitiges', tableLitigesConfig);
}

function editRowLitige(button, afterLoadingEditModal = () => {}, isArrivage, arrivageOrReceptionId, disputeId, disputeNumber) {
    let route = isArrivage ? 'litige_api_edit' : 'litige_api_edit_reception';
    let path = Routing.generate(route, true);
    let $modal = $('#modalEditLitige');
    let $submit = $modal.find('#submitEditLitige');
    let params = {
        disputeId
    };

    if (isArrivage) {
        params.arrivageId = arrivageOrReceptionId;
    } else {
        params.reception = arrivageOrReceptionId;
    }

    $.post(path, JSON.stringify(params), function (data) {
        $modal.find('.error-msg').html('');
        $modal.find('.modal-body').html(data.html);
        if (isArrivage) {
            $modal.find('#colisEditLitige').val(data.colis).select2();
        } else {
            Select2Old.articleReception($modal.find('.select2-autocomplete-articles'), arrivageOrReceptionId);

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

            $modal.find('#acheteursLitigeEdit').val(data.acheteurs).select2();
        }
        fillDemandeurField($modal);
        $modal.append('<input hidden class="data" name="isArrivage" value="' + isArrivage + '">');
        afterLoadingEditModal();

    }, 'json');

    $modal.find($submit).attr('value', disputeId);
    $('#disputeNumber').text(disputeNumber);
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
        domConfig: {
            needsPartialDomOverride: true,
        }
    };
    tableHistoLitige = initDataTable('tableHistoLitige', tableHistoLitigeConfig);
    tableArticleLitige = initTableArticleLitige();
}

function getCommentAndAddHisto() {
    let path = Routing.generate('add_comment', {dispute: $('#disputeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function () {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}
