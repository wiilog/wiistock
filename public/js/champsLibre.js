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

//Modal Type
let dataModalTypeNew = $("#modalNewType");
let ButtonSubmitTypeNew = $("#submitTypeNew");
let urlTypeNew = Routing.generate('type_new', true);
InitialiserModal(dataModalTypeNew, ButtonSubmitTypeNew, urlTypeNew,tableType);

//Modal Champs Libre
let dataModalChampsLibreNew = $("#modalNewChampsLibre");
let ButtonSubmitChampsLibreNew = $("#submitChampsLibreNew");
let urlChampsLibreNew = Routing.generate('champs_libre_new', true);
InitialiserModal(dataModalChampsLibreNew, ButtonSubmitChampsLibreNew, urlChampsLibreNew, tableChampsLibre);

let dataModalChampsLibreDelete = $("#modalDeleteChampsLibre");
let ButtonSubmitChampsLibreDelete = $("#submitChampsLibreDelete");
let urlChampsLibreDelete = Routing.generate('champs_libre_delete',{'id': id}, true);
console.log(urlChampsLibreDelete);
DeleteModal(dataModalChampsLibreDelete, ButtonSubmitChampsLibreDelete, urlChampsLibreDelete, tableChampsLibre);

