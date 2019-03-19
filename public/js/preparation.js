var path = Routing.generate('preparation_api');
var table = $('#table_id').DataTable({
    "language": {
        "url": "/js/i18n/dataTableLanguage.json"
    },
    ajax: path,
    columns: [
        { "data": 'Numéro' },
        { "data": 'Date' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});

var pathArticle = Routing.generate('preparation_article_api', {'id': id});
var tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        "url": "/js/i18n/dataTableLanguage.json"
    },
    ajax: pathArticle,
    columns: [
        { "data": 'Référence' },
        { "data": 'Quantité' },
        { "data": 'Action' },
    ],
});

let modalNewArticle = $("#modalNewArticle");
let submitNewArticle = $("#submitNewArticle");
let urlNewArticle = Routing.generate('preparation_ajout_article', true);
InitialiserModal(modalNewArticle, submitNewArticle, urlNewArticle, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('preparation_ajout_article_delete', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);
