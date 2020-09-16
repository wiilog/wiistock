let pathChauffeur = Routing.generate('chauffeur_api', true);
let tableChauffeurConfig = {
    ajax: {
        "url": pathChauffeur,
        "type": "POST"
    },
    order: [[1, 'desc']],
    columns: [
        { "data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis' },
        { "data": 'Nom', 'name': 'Nom', 'title': 'Nom' },
        { "data": 'Prénom', 'name': 'Prénom', 'title': 'Prénom' },
        { "data": 'DocumentID', 'name': 'DocumentID', 'title': 'DocumentID' },
        { "data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur'},
    ],
    columnDefs: [
        {
            "orderable" : false,
            "targets" : 0
        },
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
};
let tableChauffeur = initDataTable('tableChauffeur_id', tableChauffeurConfig);

let modalNewChauffeur = $("#modalNewChauffeur");
let submitNewChauffeur = $("#submitNewChauffeur");
let urlNewChauffeur = Routing.generate('chauffeur_new', true);
InitModal(modalNewChauffeur, submitNewChauffeur, urlNewChauffeur, {tables: [tableChauffeur]});

let modalModifyChauffeur = $('#modalEditChauffeur');
let submitModifyChauffeur = $('#submitEditChauffeur');
let urlModifyChauffeur = Routing.generate('chauffeur_edit', true);
InitModal(modalModifyChauffeur, submitModifyChauffeur, urlModifyChauffeur, {tables: [tableChauffeur]});

let modalDeleteChauffeur = $('#modalDeleteChauffeur');
let submitDeleteChauffeur = $('#submitDeleteChauffeur');
let urlDeleteChauffeur = Routing.generate('chauffeur_delete', true);
InitModal(modalDeleteChauffeur, submitDeleteChauffeur, urlDeleteChauffeur, {tables: [tableChauffeur]});
