import Select2Old from "@app/select2-old";

let tableChauffeur;

$(function() {
    initChauffeurTable();

    Select2Old.carrier($('.ajax-autocomplete-transporteur'));

    let $modalNewChauffeur = $("#modalNewChauffeur");
    let $submitNewChauffeur = $("#submitNewChauffeur");
    let urlNewChauffeur = Routing.generate('chauffeur_new', true);
    InitModal($modalNewChauffeur, $submitNewChauffeur, urlNewChauffeur, {tables: [tableChauffeur], formData: true});

    let $modalModifyChauffeur = $('#modalEditChauffeur');
    let $submitModifyChauffeur = $('#submitEditChauffeur');
    let urlModifyChauffeur = Routing.generate('chauffeur_edit', true);
    InitModal($modalModifyChauffeur, $submitModifyChauffeur, urlModifyChauffeur, {tables: [tableChauffeur]});

    let $modalDeleteChauffeur = $('#modalDeleteChauffeur');
    let $submitDeleteChauffeur = $('#submitDeleteChauffeur');
    let urlDeleteChauffeur = Routing.generate('chauffeur_delete', true);
    InitModal($modalDeleteChauffeur, $submitDeleteChauffeur, urlDeleteChauffeur, {tables: [tableChauffeur]});
});

function initChauffeurTable() {
    let pathChauffeur = Routing.generate('chauffeur_api', true);
    let tableChauffeurConfig = {
        ajax: {
            "url": pathChauffeur,
            "type": "POST"
        },
        order: [['Nom', 'desc']],
        columns: [
            { "data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false },
            { "data": 'Nom', 'name': 'Nom', 'title': 'Nom' },
            { "data": 'Prénom', 'name': 'Prénom', 'title': 'Prénom' },
            { "data": 'DocumentID', 'name': 'DocumentID', 'title': 'DocumentID' },
            { "data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur'},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    tableChauffeur = initDataTable('tableChauffeur_id', tableChauffeurConfig);
}


