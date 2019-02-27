console.log('hello');

var urlApiType = Routing.generate('typeApi', true);

var table = $('#tableType_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": urlApiType,
        "type": "POST"
    },
    columns: [
        { "data": 'Label' },
        { "data": 'Actions' },
    ],
});

var table = $('#tableChampslibre_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": urlApiType,
        "type": "POST"
    },
    columns: [
        { "data": 'Label' },
        { "data": 'Actions' },
    ],
});


var dataModal = $("#modalNewType");
var ButtonSubmit = $("#submitTypeNew");
var urlTypeNew = Routing.generate('type_new', true)
InitialiserModal(dataModal,ButtonSubmit,urlTypeNew);