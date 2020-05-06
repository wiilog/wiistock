$('.select2').select2();

let pathFournisseur = Routing.generate('fournisseur_api');
let tableFournisseurConfig = {
    processing: true,
    serverSide: true,
    paging: true,
    scrollX: true,
    order: [[1, 'desc']],
    ajax: {
        "url": pathFournisseur,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', title: '', className: 'noVis'},
        {"data": 'Nom', title: 'Nom'},
        {"data": 'Code de référence', title: 'Code de référence'},
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true
    },
    columnDefs: [
        {
            "orderable": false,
            "targets": 0
        }
    ]
};
let tableFournisseur = initDataTable('tableFournisseur_id', tableFournisseurConfig);

let modalNewFournisseur = $("#modalNewFournisseur");
let submitNewFournisseur = $("#submitNewFournisseur");
let urlNewFournisseur = Routing.generate('fournisseur_new', true);
InitialiserModal(modalNewFournisseur, submitNewFournisseur, urlNewFournisseur, tableFournisseur, displayErrorFournisseur, false);

let ModalDeleteFournisseur = $("#modalDeleteFournisseur");
let SubmitDeleteFournisseur = $("#submitDeleteFournisseur");
let urlDeleteFournisseur = Routing.generate('fournisseur_delete', true)
InitialiserModal(ModalDeleteFournisseur, SubmitDeleteFournisseur, urlDeleteFournisseur, tableFournisseur);

let modalModifyFournisseur = $('#modalEditFournisseur');
let submitModifyFournisseur = $('#submitEditFournisseur');
let urlModifyFournisseur = Routing.generate('fournisseur_edit', true);
InitialiserModal(modalModifyFournisseur, submitModifyFournisseur, urlModifyFournisseur, tableFournisseur);

function displayErrorFournisseur(data) {
    let modal = $('#modalNewFournisseur');
    displayError(modal, data.msg, data.success);
}
