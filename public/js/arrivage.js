$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Demandeur',
    }
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableService = $('#tableArrivages').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathArrivage,
        "type": "POST"
    },
    columns: [
        { "data": "NumArrivage", 'name': 'NumArrivage', 'title': "N° d'arrivage" },
        { "data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur' },
        { "data": 'NoTrackingTransp', 'name': 'NoTrackingTransp', 'title': 'N° tracking transporteur' },
        { "data": 'NumBL', 'name': 'NumBL', 'title': 'N° commande / BL' },
        { "data": 'Fournisseur', 'name': 'Fournisseur', 'title': 'Fournisseur' },
        { "data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire' },
        { "data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
        { "data": 'Date', 'name': 'Date', 'title': 'Date' },
        { "data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur' },
    ],

});

// let modalNewService = $("#modalNewService");
// let submitNewService = $("#submitNewService");
// let urlNewService = Routing.generate('service_new', true);
// InitialiserModal(modalNewService, submitNewService, urlNewService, tableService);
//
// let modalModifyService = $('#modalEditService');
// let submitModifyService = $('#submitEditService');
// let urlModifyService = Routing.generate('service_edit', true);
// InitialiserModal(modalModifyService, submitModifyService, urlModifyService, tableService);
//
// let modalDeleteService = $('#modalDeleteService');
// let submitDeleteService = $('#submitDeleteService');
// let urlDeleteService = Routing.generate('service_delete', true);
// InitialiserModal(modalDeleteService, submitDeleteService, urlDeleteService, tableService);