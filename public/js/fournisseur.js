$('.select2').select2();

let pathFournisseur = Routing.generate('fournisseur_api');
let tableFournisseur = $('#tableFournisseur_id').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
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
let urlNewFournisseur = Routing.generate('fournisseur_new', true);
InitialiserModal(modalNewFournisseur, submitNewFournisseur, urlNewFournisseur, tableFournisseur);

let ModalDeleteFournisseur = $("#modalDeleteFournisseur");
let SubmitDeleteFournisseur = $("#submitDeleteFournisseur");
let urlDeleteFournisseur = Routing.generate('fournisseur_delete', true)
InitialiserModal(ModalDeleteFournisseur, SubmitDeleteFournisseur, urlDeleteFournisseur, tableFournisseur);

let modalModifyFournisseur = $('#modalEditFournisseur');
let submitModifyFournisseur = $('#submitEditFournisseur');
let urlModifyFournisseur = Routing.generate('fournisseur_edit', true);
InitialiserModal(modalModifyFournisseur, submitModifyFournisseur, urlModifyFournisseur,  tableFournisseur);