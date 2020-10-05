$('.select2').select2();

$(function () {
    //fill l'input acheteurs (modalNewLititge)
    let modal = $('#modalNewLitige');
    let inputAcheteurs = $('#acheteursLitigeHidden').val();
    let acheteurs = inputAcheteurs.split(',');
    let $acheteursLitige = modal.find('#acheteursLitige');
    acheteurs.forEach((value) => {
        let option = new Option(value, value, false, false);
        $acheteursLitige.append(option);
    });
    $acheteursLitige.val(acheteurs).select2();

    let numeroCommandeListVal = $('#numeroCommandeListLitigeHidden').val();
    let numeroCommandeList = numeroCommandeListVal
        .split(',')
        .filter((numeroCommande) => Boolean(numeroCommande));
    let $numeroCommandSelect = modal.find('#numeroCommandeListLitige');
    numeroCommandeList.forEach((value) => {
        let option = new Option(value, value, false, false);
        $numeroCommandSelect.append(option);
    });
    $numeroCommandSelect.val(numeroCommandeList).select2();

    // ouvre la modale d'ajout de colis
    let addColis = $('#addColis').val();
    if (addColis) {
        $('#btnModalAddColis').click();
    }

    let printColis = Number(Boolean($('#printColis').val()));
    let printArrivage = Number(Boolean($('#printArrivage').val()));

    if (printColis || printArrivage) {
        let params = {
            arrivage: Number($('#arrivageId').val()),
            printColis: printColis,
            printArrivage: printArrivage
        };

        window.location.href = Routing.generate('print_arrivage_bar_codes', params, true);
    }

    //édition de colis
    const modalEditPack = $('#modalEditPack');
    const submitEditPack = $('#submitEditPack');
    const urlEditPack = Routing.generate('pack_edit', true);
    InitModal(modalEditPack, submitEditPack, urlEditPack, {tables: [tableColis]});

    //suppression de colis
    let modalDeletePack = $("#modalDeletePack");
    let SubmitDeletePack = $("#submitDeletePack");
    let urlDeletePack = Routing.generate('pack_delete', true);
    InitModal(modalDeletePack, SubmitDeletePack, urlDeletePack, {tables: [tableColis], clearOnClose: true});
});

let tableHistoLitige;
function openTableHisto() {
    let pathHistoLitige = Routing.generate('histo_litige_api', {litige: $('#litigeId').val()}, true);
    let tableHistoLitigeConfig = {
        ajax: {
            "url": pathHistoLitige,
            "type": "POST"
        },
        order: [[1, 'asc']],
        columns: [
            {"data": 'user', 'name': 'Utilisateur', 'title': 'Utilisateur'},
            {"data": 'date', 'name': 'date', 'title': 'Date', "type": "customDate"},
            {"data": 'commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
        ],
        domConfig: {
            needsPartialDomOverride: true,
        }
    };
    tableHistoLitige = initDataTable('tableHistoLitige', tableHistoLitigeConfig);
}
extendsDateSort('customDate');

let pathColis = Routing.generate('colis_api', {arrivage: $('#arrivageId').val()}, true);
let tableColisConfig = {
    ajax: {
        "url": pathColis,
        "type": "POST"
    },
    domConfig: {
        removeInfo: true
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns: [
        {"data": 'actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: true},
        {"data": 'nature', 'name': 'nature', 'title': 'natures.nature', translated: true},
        {"data": 'code', 'name': 'code', 'title': 'Code'},
        {"data": 'lastMvtDate', 'name': 'lastMvtDate', 'title': 'Date dernier mouvement'},
        {"data": 'lastLocation', 'name': 'lastLocation', 'title': 'Dernier emplacement'},
        {"data": 'operator', 'name': 'operator', 'title': 'Opérateur'},
    ],
    order: [[2, 'asc']]
};
let tableColis = initDataTable('tableColis', tableColisConfig);

let modalAddColis = $('#modalAddColis');
let submitAddColis = $('#submitAddColis');
let urlAddColis = Routing.generate('arrivage_add_colis', true);
InitModal(
    modalAddColis,
    submitAddColis,
    urlAddColis,
    {
        tables: [tableColis],
        success: (data) => {
            if (data.colisIds && data.colisIds.length > 0) {
                window.location.href = Routing.generate(
                    'print_arrivage_bar_codes',
                    {
                        arrivage: data.arrivageId,
                        printColis: 1
                    },
                    true);
            }

            window.location.href = Routing.generate('arrivage_show', {id: $('#arrivageId').val()})
        }
    }
);

let pathArrivageLitiges = Routing.generate('arrivageLitiges_api', {arrivage: $('#arrivageId').val()}, true);
let tableArrivageLitigesConfig = {
    domConfig: {
        removeInfo: true
    },
    ajax: {
        "url": pathArrivageLitiges,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'name': 'actions', 'title': '', orderable: false, className: 'noVis'},
        {"data": 'firstDate', 'name': 'firstDate', 'title': 'Date de création'},
        {"data": 'status', 'name': 'status', 'title': 'Statut'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'updateDate', 'name': 'updateDate', 'title': 'Date de modification'},
        {"data": 'urgence', 'name': 'urgence', 'title': 'urgence', visible: false},
    ],
    rowConfig: {
        needsColor: true,
        color: 'danger',
        needsRowClickAction: true,
        dataToCheck: 'urgence'
    },
    order: [[1, 'desc']]
};
let tableArrivageLitiges = initDataTable('tableArrivageLitiges', tableArrivageLitigesConfig);

let modalNewLitige = $('#modalNewLitige');
let submitNewLitige = $('#submitNewLitige');
let urlNewLitige = Routing.generate('litige_new', {reloadArrivage: $('#arrivageId').val()}, true);
InitModal(modalNewLitige, submitNewLitige, urlNewLitige, {tables: [tableArrivageLitiges]});

let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit_arrivage', {reloadArrivage: $('#arrivageId').val()}, true);
InitModal(modalEditLitige, submitEditLitige, urlEditLitige, {tables: [tableArrivageLitiges]});

let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('litige_delete_arrivage', true);
InitModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, {tables: [tableArrivageLitiges]});

let modalModifyArrivage = $('#modalEditArrivage');
let submitModifyArrivage = $('#submitEditArrivage');
let urlModifyArrivage = Routing.generate('arrivage_edit', true);
InitModal(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage, {success: (params) => arrivalCallback(false, params)});

let modalDeleteArrivage = $('#modalDeleteArrivage');
let submitDeleteArrivage = $('#submitDeleteArrivage');
let urlDeleteArrivage = Routing.generate('arrivage_delete', true);
InitModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage);

let $modalNewDispatch = $("#modalNewDispatch");
let $submitNewDispatch = $("#submitNewDispatch");
let $submitNewDispatchWithBL = $("#submitNewDispatchWithBL");
let urlDispatchNew = Routing.generate('dispatch_new', true);
let urlDispatchNewWithBL = Routing.generate('dispatch_new', {printDeliveryNote: 1}, true);
InitModal($modalNewDispatch, $submitNewDispatch, urlDispatchNew);
InitModal($modalNewDispatch, $submitNewDispatchWithBL, urlDispatchNewWithBL);

let originalText = '';

function editRowArrivage(button) {
    let path = Routing.generate('arrivage_edit_api', true);
    let modal = $('#modalEditArrivage');
    let submit = $('#submitEditArrivage');
    let id = button.data('id');
    let params = {id: id};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        const quillEdit = initEditor('.editor-container-edit');
        if (quillEdit) {
            originalText = quillEdit.getText();
        }
        modal.find('#acheteursEdit').val(data.acheteurs).select2();
        modal.find('.list-multiple').select2();
        initDateTimePicker('.date-cl');
        Select2.initFree($('.select2-free'));
    }, 'json');

    modal.find(submit).attr('value', id);
}

function editRowLitigeArrivage(button, afterLoadingEditModal = () => {}, arrivageId, litigeId, disputeNumber) {
    let path = Routing.generate('litige_api_edit', true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');

    let params = {
        litigeId: litigeId,
        arrivageId: arrivageId
    };

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        modal.find('#colisEditLitige').val(data.colis).select2();
        fillDemandeurField(modal);
        afterLoadingEditModal()
    }, 'json');

    modal.find(submit).attr('value', litigeId);
    $('#disputeNumberArrival').text(disputeNumber);
}

function deleteRowArrivage(button, modal, submit, hasLitige) {
    deleteRow(button, modal, submit);
    let hasLitigeText = modal.find('.hasLitige');
    if (hasLitige) {
        hasLitigeText.removeClass('d-none');
    } else {
        hasLitigeText.addClass('d-none');
    }
}

function getCommentAndAddHisto()
{
    let path = Routing.generate('add_comment', {litige: $('#litigeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function (response) {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}

function removePackInDispatchModal($button) {
    $button
        .closest('[data-multiple-key]')
        .remove();
}
