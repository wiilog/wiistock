console.log('hello');


const urlApiType = Routing.generate('typeApi', true);
let tableType = $('#tableType_id').DataTable({
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


const urlApiChampsLibre = Routing.generate('champsLibreApi', {'id': id},true)
let tableChampsLibre = $('#tableChampslibre_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": urlApiChampsLibre,
        "type": "POST"
    },
    columns: [
        { "data": 'Label' },
        { "data": 'Typage' },
        { "data": 'Valeur par défaut' },
        { "data": 'Actions' },
    ],
});


var dataModal = $("#modalNewType");
var ButtonSubmit = $("#submitTypeNew");
var urlTypeNew = Routing.generate('type_new', true);
InitialiserModal(dataModal,ButtonSubmit,urlTypeNew,tableType);

var dataModal = $("#modalNewChampsLibre");
var ButtonSubmit = $("#submitChampsLibreNew");
var urlTypeNew = Routing.generate('champs_libre_new', true);
InitialiserModal(dataModal,ButtonSubmit,urlTypeNew,tableChampsLibre);