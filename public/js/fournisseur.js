

let pathFournisseur = Routing.generate('fournisseur_api');
let tableFournisseur = $('#tableFournisseur_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{
        "url": pathFournisseur,
        "type": "POST"
    }, 
        columns: [
        { "data": 'Nom' },
        { "data": 'Code de référence' },
        { "data": 'Actions' },
    ],
});

let modalNewFournisseur = $("#modalNewFournisseur"); 
let submitNewFournisseur = $("#submitNewFournisseur");
let urlNewFournisseur = Routing.generate('creation_fournisseur', true);
InitialiserModal(modalNewFournisseur, submitNewFournisseur, urlNewFournisseur, tableFournisseur);

let ModalDeleteFournisseur = $("#modalDeleteFournisseur");
let SubmitDeleteFournisseur = $("#submitDeleteFournisseur");
let urlDeleteFournisseur = Routing.generate('fournisseur_delete', true)
InitialiserModal(ModalDeleteFournisseur, SubmitDeleteFournisseur, urlDeleteFournisseur, tableFournisseur);

let modalModifyFournisseur = $('#modalEditFournisseur');
let submitModifyFournisseur = $('#submitEditFournisseur');
let urlModifyFournisseur = Routing.generate('fournisseur_edit', true);
InitialiserModal(modalModifyFournisseur, submitModifyFournisseur, urlModifyFournisseur,  tableFournisseur);


$('#myTab button').on('click', function (e) {
    $(this).siblings().removeClass('data');
    $(this).addClass('data');
  })