// $( document ).ready(function () {
//     $('#modalNewArticle').modal('show')
// })

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

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let pathDeleteArticle = Routing.generate('ligne_article_delete', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, pathDeleteArticle, tableArticle);

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let pathEditArticle = Routing.generate('article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, pathEditArticle, tableArticle);



//DEMANDE
let pathDemande = Routing.generate('demande_api', true);
let tableDemande = $('#table_demande').DataTable({
    order: [[0, "desc"]],
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json",
    },
    ajax: {
        "url": pathDemande,
        "type": "POST",
    },
    columns: [
        { "data": 'Date' },
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

function updateQuantity(input) {
    let params = {
        refArticleId: input.val()
    };

    $.post(Routing.generate('get_quantity_ref_article'), params, function(data) {
        let modalBody = input.closest('.modal-body');
        modalBody.find('#in-stock').val(data);
        modalBody.find('#quantite').attr('max', data);


    }, 'json');
}

$('.ajax-autocomplete').select2({
    ajax: {
        url: Routing.generate('get_ref_articles'),
        dataType: 'json',
        delay: 250,
    },
    language: {
        inputTooShort: function() {
            return 'Veuillez entrer au moins 1 caractère.';
        },
        searching: function() {
            return 'Recherche en cours...';
        },
        noResults: function() {
            return 'Aucun résultat.';
        }
    },
    minimumInputLength: 1,
});
