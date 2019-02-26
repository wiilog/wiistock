var tablePath = Routing.generate('demande_api', true);
var table = $('#table_demande').DataTable({
    order: [[0, "desc"]],
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": tablePath,
        "type": "POST",

    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Numéro' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});

var modalPath = Routing.generate('creation_demande', true);
var dataModal = $("#dataModalCenter");
var ButtonSubmit = $("#submitButton");
InitialiserModal(dataModal, ButtonSubmit, modalPath);

$('#filtre').ajaxSend()