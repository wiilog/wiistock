//TYPE

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
        { "data": 'Catégorie' },
        { "data": 'Actions' },
    ],
});

let dataModalTypeNew = $("#modalNewType");
let ButtonSubmitTypeNew = $("#submitTypeNew");
let urlTypeNew = Routing.generate('type_new', true);
InitialiserModal(dataModalTypeNew, ButtonSubmitTypeNew, urlTypeNew,tableType);


let dataModalTypeDelete = $("#modalDeleteType");
let ButtonSubmitTypeDelete = $("#submitDeleteType");
let urlTypeDelete = Routing.generate('type_delete', true);
InitialiserModal(dataModalTypeDelete, ButtonSubmitTypeDelete, urlTypeDelete, tableType);


//CHAMPS LIBRE

const urlApiChampsLibre = Routing.generate('champsLibreApi', {'id': id},true);
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

let dataModalChampsLibreNew = $("#modalNewChampsLibre");
let ButtonSubmitChampsLibreNew = $("#submitChampsLibreNew");
let urlChampsLibreNew = Routing.generate('champs_libre_new', true);
InitialiserModal(dataModalChampsLibreNew, ButtonSubmitChampsLibreNew, urlChampsLibreNew, tableChampsLibre);

let dataModalChampsLibreDelete = $("#modalDeleteChampsLibre");
let ButtonSubmitChampsLibreDelete = $("#submitChampsLibreDelete");
let urlChampsLibreDelete = Routing.generate('champs_libre_delete', true);
InitialiserModal(dataModalChampsLibreDelete, ButtonSubmitChampsLibreDelete, urlChampsLibreDelete, tableChampsLibre);