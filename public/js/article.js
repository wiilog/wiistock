$('.select2').select2();

var pathArticle = Routing.generate('article_api', true);
var tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{ 
        "url": pathArticle,
        "type": "POST"
    },
    columns:[
        { "data": 'Référence' },
        { "data": 'Statut' },
        { "data": 'Libellé' },
        { "data": 'Référence article' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let urlEditArticle = Routing.generate('reception_article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

var editorEditArticleAlreadyDone = false;
function initEditArticleEditor(modal) {
    if (!editorEditArticleAlreadyDone) {
        initEditor(modal);
        editorEditArticleAlreadyDone = true;
    }
};