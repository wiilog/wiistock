$('.select2').select2();

var pathAlerte = Routing.generate('alerte_api', true);
var tableAlerte = $('#tableAlerte_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url":pathAlerte,
        "type": "POST"
},
    columns: [
        { "data": 'Code' },
        { "data": 'Article Référence' },
        { "data": 'Quantité en stock' },
        { "data": 'Seuil limite' },
        { "data": 'Seuil' },
        { "data": 'Utilisateur' },
        { "data": 'Actions'},
    ],
});

let modalNewAlerte = $("#modalNewAlerte"); 
let submitNewAlerte = $("#submitNewAlerte");
let urlNewAlerte = Routing.generate('alerte_new', true);
InitialiserModal(modalNewAlerte, submitNewAlerte, urlNewAlerte, tableAlerte);

let ModalDeleteAlerte = $("#modalDeleteAlerte");
let SubmitDeleteAlerte = $("#submitDeleteAlerte");
let urlDeleteAlerte = Routing.generate('alerte_delete', true)
InitialiserModal(ModalDeleteAlerte, SubmitDeleteAlerte, urlDeleteAlerte, tableAlerte);

let modalModifyAlerte = $('#modalEditAlerte');
let submitModifyAlerte = $('#submitEditAlerte');
let urlModifyAlerte = Routing.generate('alerte_edit', true);
InitialiserModal(modalModifyAlerte, submitModifyAlerte, urlModifyAlerte, tableAlerte);
