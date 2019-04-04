$('.select2').select2();

var path = Routing.generate('preparation_api');
var table = $('#table_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
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
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: pathArticle,
    columns: [
        { "data": 'Référence CEA' },
        { "data": 'Libellé' },
        { "data": 'Quantité' },
        { "data": 'Action' },
    ],
});

let modalNewArticle = $("#modalNewArticle");
let submitNewArticle = $("#submitNewArticle");
let urlNewArticle = Routing.generate('preparation_add_article', true);
InitialiserModal(modalNewArticle, submitNewArticle, urlNewArticle, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('preparation_delete_article', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);
