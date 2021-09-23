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
    initDateTimePicker();
    Select2Old.init($('#carriers'), 'Transporteurs');
    Select2Old.init($('#statut'), 'Statuts');
    Select2Old.init($('#litigeOrigin'), 'Origines');
    Select2Old.user($('.ajax-autocomplete-user:eq(0)'), 'Acheteurs');
    Select2Old.user($('.ajax-autocomplete-user:eq(1)'), 'Déclarant');
    Select2Old.dispute($('.ajax-autocomplete-dispute'),'Numéro de litige');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_LITIGE_ARR);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
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
            {"data": 'actions', 'name': 'actions', 'title': '', 'orderable': false, className: 'noVis'},
            {"data": 'type', 'name': 'type', 'title': 'Type'},
            {"data": 'disputeNumber', 'name': 'disputeNumber', 'title': 'Numéro du litige'},
            {"data": "arrivalNumber", 'name': "arrivalNumber", 'title': 'arrivage.n° d\'arrivage', translated: true, className: 'noVis'},
            {"data": 'receptionNumber', 'name': "receptionNumber", 'title': 'réception.n° de réception', translated: true, className: 'noVis'},
            {"data": 'buyers', 'name': 'buyers', 'title': 'Acheteur', 'orderable': false},
            {"data": 'declarant', 'name': 'declarant', 'title': 'Déclarant'},
            {"data": 'numCommandeBl', 'name': 'numCommandeBl', 'title': 'N° commande / BL'},
            {"data": 'command', 'name': 'command', 'title': 'N° ligne', 'orderable': false},
            {"data": 'provider', 'name': 'provider', 'title': 'Fournisseur'},
            {"data": 'references', 'name': 'references', 'title': 'Référence', 'orderable': false},
            {"data": 'lastHistoric', 'name': 'lastHistoric', 'title': 'Dernier historique', 'orderable': false},
            {"data": 'creationDate', 'name': 'creationDate', 'title': 'Créé le'},
            {"data": 'updateDate', 'name': 'updateDate', 'title': 'Modifié le'},
            {"data": 'status', 'name': 'status', 'title': 'Statut'},
            {"data": 'urgence', 'name': 'urgence', 'title': 'urgence', 'visible': false, 'class': 'noVis'},
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

function editRowLitige(button, afterLoadingEditModal = () => {}, isArrivage, arrivageOrReceptionId, litigeId, disputeNumber) {
    let route = isArrivage ? 'litige_api_edit' : 'litige_api_edit_reception';
    let path = Routing.generate(route, true);
    let $modal = $('#modalEditLitige');
    let $submit = $modal.find('#submitEditLitige');
    let params = {
        litigeId: litigeId
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

    $modal.find($submit).attr('value', litigeId);
    $('#disputeNumber').text(disputeNumber);
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
        domConfig: {
            needsPartialDomOverride: true,
        }
    };
    tableHistoLitige = initDataTable('tableHistoLitige', tableHistoLitigeConfig);
    tableArticleLitige = initTableArticleLitige();
}

function getCommentAndAddHisto() {
    let path = Routing.generate('add_comment', {litige: $('#litigeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function () {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}
