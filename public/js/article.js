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
        { "data": 'Référence' },
        { "data": 'Statut' },
        { "data": 'Reférence article' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

let modal = $('#modalModify');
let submit = modal.find('#modifySubmit');
modifyModal(modal, submit, table);