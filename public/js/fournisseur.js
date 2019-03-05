let pathFournisseur = Routing.generate('fournisseur_api');
let tableFournisseur = $('#tableFournisseur_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: pathFournisseur,
    columns: [
        { "data": 'Nom' },
        { "data": 'Code de réference' },
        { "data": 'Actions' },
    ],
});

let ModalDeleteFournisseur = $("#modalDeleteFournisseur");
let SubmitDeleteFournisseur = $("#submitDeleteFournisseur");
let urlDeleteFournisseur = Routing.generate('fournisseur_delete', true)
InitialiserModal(ModalDeleteFournisseur, SubmitDeleteFournisseur, urlDeleteFournisseur, tableFournisseur);