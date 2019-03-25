$('.select2').select2();

var pathCollecte = Routing.generate('collectes_api', true);
var table = $('#tableCollecte_id').DataTable({
       "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathCollecte,
        "type": "POST"
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Objet' },
        { "data": 'Statut' },
        { "data": 'Actions' }
    ],
});

let modalNewCollecte = $("#modalNewCollecte");
let SubmitNewCollecte = $("#submitNewCollecte");
let urlNewCollecte = Routing.generate('collecte_create', true)
InitialiserModal(modalNewCollecte, SubmitNewCollecte, urlNewCollecte, table);

let modalDeleteCollecte = $("#modalDeleteCollecte");
let submitDeleteCollecte = $("#submitDeleteCollecte");
let urlDeleteCollecte = Routing.generate('collecte_delete', true)
InitialiserModal(modalDeleteCollecte, submitDeleteCollecte, urlDeleteCollecte, table);

let modalModifyCollecte = $('#modalEditCollecte');
let submitModifyCollecte = $('#submitEditCollecte');
let urlModifyCollecte = Routing.generate('collecte_edit', true);
InitialiserModal(modalModifyCollecte, submitModifyCollecte, urlModifyCollecte, table);


//AJOUTE_ARTICLE
let pathAddArticle = Routing.generate('collecte_article_api', { 'id': id }, true);
let tableArticle = $('#tableArticle_id').DataTable({
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence CEA' },
        { "data": 'Libellé' },
        { "data": 'Emplacement' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

let modal = $("#modalNewArticle");
let submit = $("#submitNewArticle");
let url = Routing.generate('collecte_add_article', true);
InitialiserModal(modal, submit, url, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('collecte_remove_article', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);



// $('#addRow').on('click', function() {
//     $.getJSON('{{ path('modal_add_article') }}', function(data) {
//         let modal = $('#modalAddArticle');
//         modal.find('.modal-body').html(data.html);
//         $('.select2').select2(); //TODO CG

//         modal.find('.save').on('click', function() {
//             addRow($(this));
//         });

//         modal.find('#code').on('change', function() {
//            displayQuantity($(this));
//         });
//     });
// });

// $('#modalDeleteCollecte').find('.save').on('click', function() {
//     deleteCollecte($(this));
// });

// $('#modalModifyArticle').find('.save').on('click', function() {
//     modifyArticle($(this));
// });

// var path = Routing.generate('articles_by_collecte');
// $('#table-list-articles').DataTable({
//     "language": {
//         "url": "/js/i18n/dataTableLanguage.json",
//     },
//     "pageLength": 5,
//     "ajax":{
//         "url": path,
//         "data" : { collecteId: {{ collecte.id }} },
//         "type": "POST"
//     },
//     columns: [
//         { "data": 'Nom' },
//         { "data": 'Statut' },
//         { "data": 'Référence Article' },
//         { "data": 'Emplacement' },
//         { "data": 'Destination' },
//         { "data": 'Quantité à collecter' },
//         { "data": 'Actions'}
//     ],
// });