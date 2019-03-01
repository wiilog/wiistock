var path = Routing.generate('alerte_api', true);
var table = $('#table_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: path,
    columns: [
        { "data": 'Nom' },
        { "data": 'Code' },
        { "data": 'Seuil' },
        { "data": 'Actions'},
    ],
});

let modal = $('#modalModify');
let submit = modal.find('#modifySubmit');
modifyModal(modal, submit, table);

var modalPath = Routing.generate('createAlerte', true);
var dataModal = $("#dataModalCenter");
var ButtonSubmit = $("#submitButton");
InitialiserModal(dataModal, ButtonSubmit, modalPath, table);