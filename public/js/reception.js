
var modal = $("#addArticleModal"); 
var submit = $("#addArticleSubmit");
var url = Routing.generate('reception_addArticle', true);

InitialiserModal(modal, submit, url);

var dataModal = $("#dataModalCenter");
var ButtonSubmit = $("#submitButton");
var urlReceptionIndex = Routing.generate('createReception', true)

InitialiserModal(dataModal, ButtonSubmit, urlReceptionIndex);

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


