$('.select2').select2();

let pathFournisseur = Routing.generate('fournisseur_api');
let tableFournisseurConfig = {
    processing: true,
    serverSide: true,
    paging: true,
    order: [[1, 'desc']],
    ajax: {
        "url": pathFournisseur,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', title: '', className: 'noVis', orderable: false},
        {"data": 'Nom', title: 'Nom'},
        {"data": 'Code de référence', title: 'Code de référence'},
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true
    }
};
let tableFournisseur = initDataTable('tableFournisseur_id', tableFournisseurConfig);

let modalNewFournisseur = $("#modalNewFournisseur");
let submitNewFournisseur = $("#submitNewFournisseur");
let urlNewFournisseur = Routing.generate('fournisseur_new', true);
InitModal(modalNewFournisseur, submitNewFournisseur, urlNewFournisseur, {tables: [tableFournisseur]});

let ModalDeleteFournisseur = $("#modalDeleteFournisseur");
let SubmitDeleteFournisseur = $("#submitDeleteFournisseur");
let urlDeleteFournisseur = Routing.generate('fournisseur_delete', true)
InitModal(ModalDeleteFournisseur, SubmitDeleteFournisseur, urlDeleteFournisseur, {tables: [tableFournisseur]});

let modalModifyFournisseur = $('#modalEditFournisseur');
let submitModifyFournisseur = $('#submitEditFournisseur');
let urlModifyFournisseur = Routing.generate('fournisseur_edit', true);
InitModal(modalModifyFournisseur, submitModifyFournisseur, urlModifyFournisseur, {tables: [tableFournisseur]});
