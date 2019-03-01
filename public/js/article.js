/* function editRow(button) {
    let quantity = button.data('quantity');
    let name = button.data('name');
    let id = button.data('id');
    let modal = $('#modalModifyLigneArticle');
    modal.find('.quantity').val(quantity);
    modal.find('.quantity').attr('max', quantity); //TODO CG il faudrait récupérer la valeur de la quantité de l'article
    modal.find('.ligne-article').html(name);
    modal.data('id', id); //TODO CG trouver + propre
} */

var path = Routing.generate('article_api', true);
var table = $('#table_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{ 
        "url": path,
        "type": "POST"
    },
    columns:
    [
        { "data": 'Nom' },
        { "data": 'Statut' },
        { "data": 'Reférence article' },
        { "data": 'Emplacement' },
        { "data": 'Destination' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

let modal = $('#modalModify');
let submit = modal.find('#modifySubmit');
modifyModal(modal, submit, table);