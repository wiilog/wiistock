
//RECEPTION
var path = Routing.generate('reception_api', true);
var table = $('#tableReception_id').DataTable({
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

let modalReceptionNew = $("#dataModalCenter");
let SubmitNewReception = $("#submitButton");
let urlReceptionIndex = Routing.generate('createReception', true)
InitialiserModal(modalReceptionNew, SubmitNewReception, urlReceptionIndex, table);

let ModalDelete = $("#modalDeleteReception");
let SubmitDelete = $("#submitDeleteReception");
let urlDeleteReception = Routing.generate('reception_delete', true)
InitialiserModal(ModalDelete, SubmitDelete, urlDeleteReception, table);

let modalModifyReception = $('#modalEditReception');
let submitModifyReception = $('#submitEditReception');
let urlModifyReception = Routing.generate('reception_edit', true);
InitialiserModal(modalModifyReception, submitModifyReception, urlModifyReception,  table);


//AJOUTE_ARTICLE
let pathAddArticle = Routing.generate('reception_article_api', {'id': id}, true);
let tableArticle = $('#tableArticle_id').DataTable({
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{ 
        "url": pathAddArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Libellé' },
        { "data": 'Référence' },
        { "data": 'Référence CEA' },
        { "data": 'Actions' }
    ],
});

let modal = $("#addArticleModal"); 
let submit = $("#addArticleSubmit");
let url = Routing.generate('reception_addArticle', true);
InitialiserModal(modal, submit, url, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle"); 
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('reception_article_delete', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

// let pathName = 'modifyReception';
// let modal = $('#modalModify');
// let submit = modal.find('#modifySubmit');
// modifyModal(modal, submit, table, pathName);

// var modalPath = Routing.generate('createReception', true);
// var dataModal = $("#dataModalCenter");
// var ButtonSubmit = $("#submitButton");
// InitialiserModal(dataModal, ButtonSubmit, modalPath, table);