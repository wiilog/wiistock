
var modal = $("#addArticleModal"); 
var submit = $("#addArticleSubmit");
var url = Routing.generate('receptions_addArticle', true);

InitialiserModal(modal, submit, url);


var table = $('#tableArticle_id').DataTable({
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
});
