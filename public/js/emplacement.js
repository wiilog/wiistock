var path = Routing.generate("emplacement_api", true);
var table = $('#table_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: path,
    columns: [
        { "data": 'Nom' },
        { "data": 'Description' },
        { "data": 'Actions' }
    ],
    buttons: [
        'copy', 'excel', 'pdf'
    ]
});

let modal = $('#modalModify');
let submit = modal.find('#modifySubmit');
modifyModal(modal, submit, table);

var path = Routing.generate('createEmplacement', true);
let dataModal = $("#dataModalCenter");
var ButtonSubmit = $("#submitButton");


InitialiserModal(dataModal, ButtonSubmit, path, table);