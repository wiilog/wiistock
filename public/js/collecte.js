var pathCollecte = Routing.generate('collecte_api', true);
var table = $('#tableCollecte_id').DataTable({
       "language": {
        "url": "/js/i18n/dataTableLanguage.json"
    },
    ajax: {
        "url": pathCollecte,
        "type": "POST"
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Objet' },
        { "data": 'Statut' },
        { "data": 'Actions' }
    ],
});

let modalNewCollecte = $("#modalNewCollecte");
let SubmitNewCollecte = $("#submitNewCollecte");
let urlNewCollecte = Routing.generate('collecte_create', true)
InitialiserModal(modalNewCollecte, SubmitNewCollecte, urlNewCollecte, table);

let modalDeleteCollecte = $("#modalDeleteCollecte");
let submitDeleteCollecte = $("#submitDeleteCollecte");
let urlDeleteCollecte = Routing.generate('collecte_delete', true)
console.log(urlDeleteCollecte);
InitialiserModal(modalDeleteCollecte, submitDeleteCollecte, urlDeleteCollecte, table);

let modalModifyCollecte = $('#modalEditCollecte');
let submitModifyCollecte = $('#submitEditCollecte');
let urlModifyCollecte = Routing.generate('collecte_edit', true);
InitialiserModal(modalModifyCollecte, submitModifyCollecte, urlModifyCollecte, table);


//AJOUTE_ARTICLE
// let pathAddArticle = Routing.generate('collecte_article_api', { 'id': id }, true);
// let tableArticle = $('#tableArticle_id').DataTable({
//     language: {
//         "url": "/js/i18n/dataTableLanguage.json"
//     },
//     ajax: {
//         "url": pathAddArticle,
//         "type": "POST"
//     },
//     columns: [
//         { "data": 'Libellé' },
//         { "data": 'Référence' },
//         { "data": 'Référence CEA' },
//         { "data": 'Actions' }
//     ],
// });

// let modal = $("#addArticleModal");
// let submit = $("#addArticleSubmit");
// let url = Routing.generate('collecte_addArticle', true);
// InitialiserModal(modal, submit, url, tableArticle);

// let modalDeleteArticle = $("#modalDeleteArticle");
// let submitDeleteArticle = $("#submitDeleteArticle");
// let urlDeleteArticle = Routing.generate('reception_article_delete', true);
// InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);