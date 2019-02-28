//REFERENCE ARTICLE 

const urlApiRefArticle = Routing.generate('ref_article_api', true);
var tableRefArticle = $('#tableRefArticle_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": urlApiRefArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Libellé' },
        { "data": 'Référence' },
        { "data": 'Actions' },
    ],
});

// let dataModalRefArticleNew = $("#modalNewRefArticle");
// console.log(dataModalRefArticleNew)
// let ButtonSubmitRefArticleNew = $("#submitNewRefArticle");
// let urlRefArticleNew = Routing.generate('reference_article_new', true);
// InitialiserModal(dataModaRefArticleNew, ButtonSubmitRefArticleNew, urlRefArticleNew,tableRefArticle);

// let dataModalTypeDelete = $("#modalDeleteType");
// let ButtonSubmitTypeDelete = $("#submitDeleteType");
// let urlTypeDelete = Routing.generate('type_delete', true);
// InitialiserModal(dataModalTypeDelete, ButtonSubmitTypeDelete, urlTypeDelete, tableType);

