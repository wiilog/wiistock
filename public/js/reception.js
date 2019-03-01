var path = Routing.generate('reception_api', true);
var table = $('#table_id').DataTable({
    order: [[ 1, "desc" ]],
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{ 
        "url": path,
        "type": "POST"
    },
    columns: [
        { "data": 'Statut' },
        { "data": 'Date commande' },
        { "data": 'Date attendue' },
        { "data": 'Fournisseur' },
        { "data": 'Référence' },
        { "data": 'Actions' }
    ],
});

var modal = $("#addArticleModal"); 
var submit = $("#addArticleSubmit");
var url = Routing.generate('reception_addArticle', true);

InitialiserModal(modal, submit, url);

var dataModal = $("#dataModalCenter");
var ButtonSubmit = $("#submitButton");
var urlReceptionIndex = Routing.generate('createReception', true)

InitialiserModal(dataModal, ButtonSubmit, urlReceptionIndex, table);

var table = $('#tableArticle_id').DataTable({
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    columns: [
        { "data": 'Libellé' },
        { "data": 'Référence' },
        { "data": 'Références Articles' },
        { "data": 'Quantité à recevoir' },
        { "data": 'Quantité reçue' },
        { "data": 'Actions' }
    ],
});

let pathName = 'modifyReception';
let modal = $('#modalModify');
let submit = modal.find('#modifySubmit');
modifyModal(modal, submit, table, pathName);

var modalPath = Routing.generate('createReception', true);
var dataModal = $("#dataModalCenter");
var ButtonSubmit = $("#submitButton");
InitialiserModal(dataModal, ButtonSubmit, modalPath, table);