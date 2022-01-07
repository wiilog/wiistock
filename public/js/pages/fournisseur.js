$('.select2').select2();

let pathFournisseur = Routing.generate('fournisseur_api');
let tableFournisseurConfig = {
    processing: true,
    serverSide: true,
    paging: true,
    order: [['name', 'desc']],
    ajax: {
        "url": pathFournisseur,
        "type": "POST"
    },
    columns: [
        {data: 'Actions', title: '', className: 'noVis', orderable: false},
        {data: 'name', title: 'Nom'},
        {data: 'code', title: 'Code de référence'},
        {data: 'isPossibleCustoms', title: 'Possible douane'},
        {data: 'isUrgent', title: 'Urgent'},
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
