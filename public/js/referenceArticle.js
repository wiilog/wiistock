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

let ModalRefArticleNew = $("#modalNewRefArticle");
let ButtonSubmitRefArticleNew = $("#submitNewRefArticle");
let urlRefArticleNew = Routing.generate('reference_article_new', true);
InitialiserModal(ModalRefArticleNew, ButtonSubmitRefArticleNew, urlRefArticleNew, tableRefArticle);

let ModalDeleteRefArticle = $("#modalDeleteRefArticle");
let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
InitialiserModal(ModalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle, tableRefArticle);

$('#myTab button').on('click', function (e) {
    $(this).siblings().removeClass('data');
    $(this).addClass('data');
  })


// let modal = $('#modalModify');
// let submit = modal.find('#modifySubmit');
// modifyModal(modal, submit, table);


