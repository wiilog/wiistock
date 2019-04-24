$('.select2').select2();

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

function checkAndDeleteRow(icon) {
    let modalBody = ModalDeleteFournisseur.find('.modal-body');
    console.log(modalBody);
    let id = icon.data('id');
    let param = JSON.stringify(id);

    $.post(Routing.generate('fournisseur_check_delete'), param, function(resp) {
        modalBody.html(resp.html);
        if (resp.delete == false) {
            SubmitDeleteFournisseur.hide();
        } else {
            SubmitDeleteFournisseur.show();
            SubmitDeleteFournisseur.attr('value', id);
        }
    });
}
