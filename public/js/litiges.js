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

$(function() {
    initDateTimePicker();
    initSelect2($('#carriers'), 'Transporteurs');
    initSelect2($('#statut'), 'Statut');
    initSelect2($('#litigeOrigin'), 'Origine');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Acheteurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_LITIGE_ARR);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    initDatatableLitiges();
    InitialiserModal(modalNewLitiges, submitNewLitiges, urlNewLitiges, tableLitiges);
    initModalWithAttachments(modalEditLitige, submitEditLitige, urlEditLitige, tableLitiges);
    InitialiserModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, tableLitiges);
});

function initDatatableLitiges() {
    let pathLitiges = Routing.generate('litige_api', true);
    tableLitiges = $('#tableLitiges').DataTable({
        responsive: true,
        serverSide: true,
        processing: true,
        language: {
            url: "/js/i18n/dataTableLanguage.json",
        },
        order: [11, 'desc'],
        ajax: {
            "url": pathLitiges,
            "type": "POST",
            'dataSrc': function (json) {
                json.columnHidden.forEach(element => {
                    tableLitiges.column(element+':name').visible(false);
                });
                return json.data;
            }
        },
        'drawCallback': function() {
            overrideSearch($('#tableLitiges_filter input'), tableLitiges);
        },
        columns: [
            {"data": 'actions', 'name': 'Actions', 'title': 'Actions', 'orderable': false},
            {"data": 'type', 'name': 'Type', 'title': 'Type'},
            {"data": "arrivalNumber", 'name': "N°_d'arrivage", 'title': $('#transNoArrivage').val()},
            {"data": 'receptionNumber', 'name': "N°_de_réception", 'title': $('#transNoReception').val()},
            {"data": 'buyers', 'name': 'Acheteurs', 'title': 'Acheteurs'},
            {"data": 'numCommandeRecep', 'name': 'N°_commande_/_BL', 'title': 'N° commande / BL'},
            {"data": 'command', 'name': 'N°_ligne', 'title': 'N° ligne', 'orderable': false},
            {"data": 'provider', 'name': 'Fournisseur', 'title': 'Fournisseur'},
            {"data": 'references', 'name': 'Références', 'title': 'Références', 'orderable': false},
            {"data": 'lastHistoric', 'name': 'Dernier_historique', 'title': 'Dernier historique'},
            {"data": 'creationDate', 'name': 'Créé_le', 'title': 'Créé le'},
            {"data": 'updateDate', 'name': 'Modifié_le', 'title': 'Modifié le'},
            {"data": 'status', 'name': 'Statut', 'title': 'Statut'},
            {"data": 'urgence', 'name': 'urgence', 'title': 'urgence', 'visible': false, 'class': 'noVis'},
        ],
        headerCallback: function(thead) {
            $(thead).find('th').eq(2).attr('title', "n° d'arrivage");
            $(thead).find('th').eq(4).attr('title', "n° de réception");
        },
        dom: '<"row"<"col-4"B><"col-4"l><"col-4"f>>t<"bottom"ip>r',
        buttons: [
            {
                extend: 'colvis',
                columns: ':not(.noVis)',
                className: 'dt-btn'
            },
        ],
        rowCallback: function (row, data) {
            $(row).addClass(data.urgence ? 'table-danger' : '');
        },
        initComplete: function() {
            let $btnColvis = $('#tableLitiges_wrapper').first('.buttons-colvis');
            $btnColvis.one('click', initColVisParam);
        }
    });
}

function initColVisParam() {
    let $buttons = $(this).find('.buttons-columnVisibility');

    $buttons.on('click', function() {
        let data = {};
        $buttons.each((index, elem) => {
            let $elem = $(elem);
            data[$elem.text()] = $elem.hasClass('active');
        });
       $.post(Routing.generate('save_column_hidden_for_litiges'), data);
    });
}

function editRowLitige(button, afterLoadingEditModal = () => {}, isArrivage, arrivageOrReceptionId, litigeId) {
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

function getCommentAndAddHisto()
{
    let path = Routing.generate('add_comment', {litige: $('#litigeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function () {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}
