//ARTICLE DEMANDE
let pathArticle = Routing.generate('LigneArticle_api', { id: id }, true);
let tableArticle = $('#table-lignes').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    "processing": true,
    "ajax": {
        "url": pathArticle,
        "type": "POST"
    },
    columns:[
            {"data": 'Référence CEA'},
            {"data": 'Libellé'},
            {"data": 'Quantité'},
            {"data": 'Actions'}
    ],
});

let modalNewArticle = $("#modalNewArticle");
let submitNewArticle = $("#submitNewArticle");
let pathNewArticle = Routing.generate('ajoutLigneArticle', true);
InitialiserModal(modalNewArticle, submitNewArticle, pathNewArticle, tableArticle);

// let dataModal1 = $("#modifModalCenter");
// let ButtonSubmit1 = $("#modifsubmitButton");
// let path1 = Routing.generate('modifDemande', { id: id1 }, true);
// InitialiserModal(dataModal1, ButtonSubmit1, path1, table);



//DEMANDE
let pathDemande = Routing.generate('demande_api', true);
let tableDemande = $('#table_demande').DataTable({
    order: [[0, "desc"]],
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathDemande,
        "type": "POST",
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Date attendu' },
        { "data": 'Demandeur' },
        { "data": 'Numéro' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});


let urlNewDemande = Routing.generate('demande_new', true);
let modalNewDemande = $("#modalNewDemande");
let submitNewDemande = $("#submitNewDemande");
InitialiserModal(modalNewDemande, submitNewDemande, urlNewDemande, tableDemande);

let urlDeleteDemande = Routing.generate('demande_delete', true);
let modalDeleteDemande = $("#modalDeleteDemande");
let submitDeleteDemande = $("#submitDeleteDemande");
InitialiserModal(modalDeleteDemande, submitDeleteDemande, urlDeleteDemande, tableDemande);

let urlEditDemande = Routing.generate('demande_edit', true);
let modalEditDemande = $("#modalEditDemande");
let submitEditDemande = $("#submitEditDemande");
InitialiserModal(modalEditDemande, submitEditDemande, urlEditDemande, tableDemande);
