$('.select2').select2();
let tableLitiges;
let modalNewLitiges = $('#modalNewLitiges');
let submitNewLitiges = $('#submitNewLitiges');
let urlNewLitiges = Routing.generate('litige_new', true);
let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit', true);
let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('litige_delete', true);
let modalColumnVisible = $('#modalColumnVisibleLitige');
let submitColumnVisible = $('#submitColumnVisibleLitige');
let urlColumnVisible = Routing.generate('save_column_visible_for_litige', true);

$(function () {
    initDateTimePicker();
    initSelect2($('#carriers'), 'Transporteurs');
    initSelect2($('#statut'), 'Statuts');
    initSelect2($('#litigeOrigin'), 'Origines');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Acheteurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_LITIGE_ARR);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    initDatatableLitiges();
    InitialiserModal(modalNewLitiges, submitNewLitiges, urlNewLitiges, tableLitiges);
    initModalWithAttachments(modalEditLitige, submitEditLitige, urlEditLitige, tableLitiges);
    InitialiserModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, tableLitiges);
    InitialiserModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);
});

function initDatatableLitiges() {
    let pathLitiges = Routing.generate('litige_api', true);
    let tableLitigesConfig = {
        serverSide: true,
        processing: true,
        order: [11, 'desc'],
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
            {"data": "arrivalNumber", 'name': "arrivalNumber", 'title': $('#transNoArrivage').val()},
            {"data": 'receptionNumber', 'name': "receptionNumber", 'title': $('#transNoReception').val()},
            {"data": 'buyers', 'name': 'buyers', 'title': 'Acheteur'},
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
        headerCallback: function (thead) {
            $(thead).find('th').eq(2).attr('title', "n° d'arrivage");
            $(thead).find('th').eq(3).attr('title', "n° de réception");
        },
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
    };
    tableLitiges = initDataTable('tableLitiges', tableLitigesConfig);
}

function editRowLitige(button, afterLoadingEditModal = () => {
}, isArrivage, arrivageOrReceptionId, litigeId) {
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
            ajaxAutoArticlesReceptionInit($modal.find('.select2-autocomplete-articles'), arrivageOrReceptionId);

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

        $modal.append('<input hidden class="data" name="isArrivage" value="' + isArrivage + '">');
        afterLoadingEditModal();

    }, 'json');

    $modal.find($submit).attr('value', litigeId);
}

let tableHistoLitige;

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
