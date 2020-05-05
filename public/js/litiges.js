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

$(function () {
    initDateTimePicker();
    initSelect2($('#carriers'), 'Transporteurs');
    initSelect2($('#statut'), 'Statut');
    initSelect2($('#litigeOrigin'), 'Origine');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Acheteurs');
    registerDropdownPosition();

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
});

function initDatatableLitiges() {
    let pathLitiges = Routing.generate('litige_api', true);
    let tableLitigesConfig = {
        serverSide: true,
        processing: true,
        scrollX: true,
        order: [11, 'desc'],
        ajax: {
            "url": pathLitiges,
            "type": "POST",
            'dataSrc': function (json) {
                json.columnHidden.forEach(element => {
                    tableLitiges.column(element).visible(false);
                });
                return json.data;
            }
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns: [
            {"data": 'actions', 'name': 'Actions', 'title': '', 'orderable': false, className: 'noVis'},
            {"data": 'type', 'name': 'Type', 'title': 'Type'},
            {"data": "arrivalNumber", 'name': "N°_d'arrivage", 'title': $('#transNoArrivage').val()},
            {"data": 'receptionNumber', 'name': "N°_de_réception", 'title': $('#transNoReception').val()},
            {"data": 'buyers', 'name': 'Acheteur', 'title': 'Acheteur'},
            {"data": 'numCommandeBl', 'name': 'N°_commande_/_BL', 'title': 'N° commande / BL'},
            {"data": 'command', 'name': 'N°_ligne', 'title': 'N° ligne', 'orderable': false},
            {"data": 'provider', 'name': 'Fournisseur', 'title': 'Fournisseur'},
            {"data": 'references', 'name': 'Référence', 'title': 'Référence', 'orderable': false},
            {"data": 'lastHistoric', 'name': 'Dernier_historique', 'title': 'Dernier historique', 'orderable': false},
            {"data": 'creationDate', 'name': 'Créé_le', 'title': 'Créé le'},
            {"data": 'updateDate', 'name': 'Modifié_le', 'title': 'Modifié le'},
            {"data": 'status', 'name': 'Statut', 'title': 'Statut'},
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
            needsDangerColor: true,
            needsRowClickAction: true,
            dataToCheck: 'urgence'
        },
        initCompleteCallback: () => {
            let $btnColvis = $('#tableLitiges_wrapper').first('.buttons-colvis');
            $btnColvis.one('click', initColVisParam);
        }
    };
    tableLitiges = initDataTable('tableLitiges', tableLitigesConfig);
}

function initColVisParam() {
    let $buttons = $(this).find('.buttons-columnVisibility');

    $buttons.on('click', function () {
        let data = {};
        $buttons.each((index, elem) => {
            let $elem = $(elem);
            data[$elem.data('cv-idx')] = $elem.hasClass('active');
        });
        $.post(Routing.generate('save_column_hidden_for_litiges'), data);
    });
}

function editRowLitige(button, afterLoadingEditModal = () => {
}, isArrivage, arrivageOrReceptionId, litigeId) {
    let route = isArrivage ? 'litige_api_edit' : 'litige_api_edit_reception';
    let path = Routing.generate(route, true);
    let $modal = $('#modalEditLitige');
    let $submit = $modal.find('#submitEditLitige');

    let params = {
        litigeId: litigeId,
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
