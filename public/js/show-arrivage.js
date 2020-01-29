$('.select2').select2();

$(function () {
    //fill l'input acheteurs (modalNewLititge)
    let modal = $('#modalNewLitige');
    let inputAcheteurs = $('#acheteursLitigeHidden').val();
    let acheteurs = inputAcheteurs.split(',');
    acheteurs.forEach(value => {
        let option = new Option(value, value, false, false);
        modal.find('#acheteursLitige').append(option);
    });
    $('#acheteursLitige').val(acheteurs).select2();

    // ouvre la modale d'ajout de colis
    let addColis = $('#addColis').val();
    if (addColis) {
        $('#btnModalAddColis').click();
    }

    //check si on doit print les colis ou l'arrivage à la création
    let printColis = $('#printColis').val();
    let printArrivage = $('#printArrivage').val();
    if (printColis) {
        getDataAndPrintLabels($('#arrivageId').val());
    }
    if (printArrivage) {
        printBarcode($('#numeroArrivage').val());
    }
});

function printBarcode(code) {
    let path = Routing.generate('get_print_data', true);

    $.post(path, function (response) {
        printBarcodes([code], response, ('Etiquette_' + code + '.pdf'));
    });
}

function printLabels(data) {
    printBarcodes(data.codes, data, ('Colis arrivage ' + data.arrivage + '.pdf'));
}

let pathColis = Routing.generate('colis_api', {arrivage: $('#arrivageId').val()}, true);
let tableColis = $('#tableColis').DataTable({
    responsive: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    scrollX: true,
    ajax: {
        "url": pathColis,
        "type": "POST"
    },
    columns: [
        {"data": 'nature', 'name': 'nature', 'title': 'Nature'},
        {"data": 'code', 'name': 'code', 'title': 'Code'},
        {"data": 'lastMvtDate', 'name': 'lastMvtDate', 'title': 'Date dernier mouvement'},
        {"data": 'lastLocation', 'name': 'lastLocation', 'title': 'Dernier emplacement'},
        {"data": 'operator', 'name': 'operator', 'title': 'Opérateur'},
        {"data": 'actions', 'name': 'actions', 'title': 'Action'},
    ],
    order: [
        [1, 'asc'],
    ],
});
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
        order: [[1, 'asc']],
        columns: [
            {"data": 'user', 'name': 'Utilisateur', 'title': 'Utilisateur'},
            {"data": 'date', 'name': 'date', 'title': 'Date'},
            {"data": 'commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
        ],
        "columnDefs": [
            {
                "type": "customDate",
                "targets": 1
            },
        ],
        dom: '<"top">rt<"bottom"lp><"clear">'
    });
}
extendsDateSort('customDate');

let modalAddColis = $('#modalAddColis');
let submitAddColis = $('#submitAddColis');
let urlAddColis = Routing.generate('arrivage_add_colis', true);
InitialiserModal(modalAddColis, submitAddColis, urlAddColis, tableColis, (data) => {
    printLabels(data);
    window.location.href = Routing.generate('arrivage_show', {id: $('#arrivageId').val()})
});

let pathArrivageLitiges = Routing.generate('arrivageLitiges_api', {arrivage: $('#arrivageId').val()}, true);
let tableArrivageLitiges = $('#tableArrivageLitiges').DataTable({
    responsive: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    scrollX: true,
    ajax: {
        "url": pathArrivageLitiges,
        "type": "POST"
    },
    columns: [
        {"data": 'firstDate', 'name': 'firstDate', 'title': 'Date de création'},
        {"data": 'status', 'name': 'status', 'title': 'Statut'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'updateDate', 'name': 'updateDate', 'title': 'Date de modification'},
        {"data": 'Actions', 'name': 'actions', 'title': 'Action'},
    ],
    order: [[0, 'desc']],
});

let modalNewLitige = $('#modalNewLitige');
let submitNewLitige = $('#submitNewLitige');
let urlNewLitige = Routing.generate('litige_new', {reloadArrivage: $('#arrivageId').val()}, true);
initModalWithAttachments(modalNewLitige, submitNewLitige, urlNewLitige, tableArrivageLitiges);

let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit_arrivage', {reloadArrivage: $('#arrivageId').val()}, true);
initModalWithAttachments(modalEditLitige, submitEditLitige, urlEditLitige, tableArrivageLitiges);

let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('litige_delete', true);
InitialiserModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, tableArrivageLitiges);

let modalModifyArrivage = $('#modalEditArrivage');
let submitModifyArrivage = $('#submitEditArrivage');
let urlModifyArrivage = Routing.generate('arrivage_edit', true);
initModalWithAttachments(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage);

let modalDeleteArrivage = $('#modalDeleteArrivage');
let submitDeleteArrivage = $('#submitDeleteArrivage');
let urlDeleteArrivage = Routing.generate('arrivage_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage);

let quillEdit;
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
        quillEdit = initEditor('.editor-container-edit');
        modal.find('#acheteursEdit').val(data.acheteurs).select2();
        originalText = quillEdit.getText();
    }, 'json');

    modal.find(submit).attr('value', id);
}

function editRowLitigeArrivage(button, afterLoadingEditModal = () => {}, arrivageId, litigeId) {
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
        afterLoadingEditModal()
    }, 'json');

    modal.find(submit).attr('value', litigeId);
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

function getDataAndPrintLabels(codes) {
    let path = Routing.generate('arrivage_get_data_to_print', true);
    let param = codes;
    $.post(path, JSON.stringify(param), function (response) {
        let codeColis = [];
        let dropZones = [];
        if (response.response.exists) {
            if (response.codeColis.length === 0) {
                alertErrorMsg("Il n'y a aucun colis à imprimer.");
            } else {
                for (const code of response.codeColis) {
                    codeColis.push(code.code);
                    dropZones.push(response.dropzone);
                }
                if (!response.dropzone) dropZones = null;
                printBarcodes(codeColis, response.response, ('Etiquettes.pdf'), dropZones);
            }
        }
    });
}

function printColisBarcode(codeColis, dropzone) {
    let path = Routing.generate('get_print_data', true);

    $.post(path, function (response) {
        printBarcodes([codeColis], response, ('Etiquette colis ' + codeColis + '.pdf'), dropzone ? [dropzone] : dropzone);
    });
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
