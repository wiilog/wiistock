let pathChauffeur = Routing.generate('chauffeur_api', true);
let tableChauffeur = $('#tableChauffeur_id').DataTable({

    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathChauffeur,
        "type": "POST"
    },
    columns: [
        { "data": 'Nom', 'name': 'Nom', 'title': 'Nom' },
        { "data": 'Prénom', 'name': 'Prénom', 'title': 'Prénom' },
        { "data": 'DocumentID', 'name': 'DocumentID', 'title': 'DocumentID' },
        { "data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur'},
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
    ],
});


let modalNewChauffeur = $("#modalNewChauffeur");
let submitNewChauffeur = $("#submitNewChauffeur");
let urlNewChauffeur = Routing.generate('chauffeur_new', true);
InitialiserModal(modalNewChauffeur, submitNewChauffeur, urlNewChauffeur, tableChauffeur);

let modalModifyChauffeur = $('#modalEditChauffeur');
let submitModifyChauffeur = $('#submitEditChauffeur');
let urlModifyChauffeur = Routing.generate('chauffeur_edit', true);
InitialiserModal(modalModifyChauffeur, submitModifyChauffeur, urlModifyChauffeur, tableChauffeur);

let modalDeleteChauffeur = $('#modalDeleteChauffeur');
let submitDeleteChauffeur = $('#submitDeleteChauffeur');
let urlDeleteChauffeur = Routing.generate('chauffeur_delete', true);
InitialiserModal(modalDeleteChauffeur, submitDeleteChauffeur, urlDeleteChauffeur, tableChauffeur);

function iniTransporteur() {
    ajaxAutoCompleteTransporteurInit($('.ajax-autocompleteTransporteur'))
};
function iniTransporteurEdit() {
    ajaxAutoCompleteTransporteurInit($('.ajax-autocompleteTransporteur-edit'))
};