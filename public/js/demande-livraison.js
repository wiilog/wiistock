function editRow(button) {
    let quantity = button.data('quantity');
    let name = button.data('name');
    let id = button.data('id');
    let modal = $('#modalModifyLigneArticle');
    let submit = modal.find('#modifySubmit');
    let path = Routing.generate('modifyLigneArticle', {id: id} , true);
    modal.find('.quantity').val(quantity);
    modal.find('.quantity').attr('max', quantity); //TODO CG il faudrait récupérer la valeur de la quantité de l'article
    modal.find('.ligne-article').html(name);
    modal.data('id', id); //TODO CG trouver + propre

    InitialiserModal(modal, submit, path, table);
}


var idTable = $('#demande-id').data('id');
var path = Routing.generate('LigneArticle_api', { id: idTable }, true);
var table = $('#table-lignes').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    "processing": true,
    "ajax": {
        "url": path,
        "type": "POST"
    },
    columns:[
            {"data": 'Référence CEA'},
            {"data": 'Libellé'},
            {"data": 'Quantité'},
            {"data": 'Actions'}
    ],
});


let modal = $('#modalModify');
let submit = modal.find('#modifySubmit');
let pathName = 'modifyLigneArticle';
modifyModal(modal, submit, table, pathName);


var dataModal1 = $("#modifModalCenter");
var id1 = dataModal1.data('id');
var ButtonSubmit1 = $("#modifsubmitButton");
var path1 = Routing.generate('modifDemande', { id: id1 }, true);
InitialiserModal(dataModal1, ButtonSubmit1, path1, table);


var dataModal2 = $("#ajoutLigneModalCenter");
var id2 = dataModal2.data('id');
var ButtonSubmit2 = $("#ajoutsubmitButton");
var path2 = Routing.generate('ajoutLigneArticle', { id: id2 }, true);
InitialiserModal(dataModal2, ButtonSubmit2, path2, table);


var tablePath = Routing.generate('demande_api', true);
var table = $('#table_demande').DataTable({
    order: [[0, "desc"]],
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": tablePath,
        "type": "POST",

    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Numéro' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});


var modalPath = Routing.generate('creation_demande', true);
var dataModal = $("#dataModalCenter");
var ButtonSubmit = $("#submitButton");
InitialiserModal(dataModal, ButtonSubmit, modalPath, table);
