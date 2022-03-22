$(function () {
    const table = initNatureTable();

    let modalNewNature = $("#modalNewNature");
    let submitNewNature = $("#submitNewNature");
    let urlNewNature = Routing.generate('nature_new', true);
    InitModal(modalNewNature, submitNewNature, urlNewNature, {tables: [table]});

    let modalEditNature = $('#modalEditNature');
    let submitEditNature = $('#submitEditNature');
    let urlEditNature = Routing.generate('nature_edit', true);
    InitModal(modalEditNature, submitEditNature, urlEditNature, {tables: [table]});

    let modalDeleteNature = $("#modalDeleteNature");
    let submitDeleteNature = $("#submitDeleteNature");
    let urlDeleteNature = Routing.generate('nature_delete', true)
    InitModal(modalDeleteNature, submitDeleteNature, urlDeleteNature, {tables: [table]});
});

function initNatureTable() {
    let pathNature = Routing.generate('nature_param_api', true);
    let tableNatureConfig = {
        ajax: {
            url: pathNature,
            type: "POST"
        },
        order: [['label', 'asc']],
        columns: [
            {data: 'actions', title: '', className: 'noVis', orderable: false},
            {data: 'label', title: 'Libellé'},
            {data: 'code', title: 'Code'},
            {data: 'defaultQuantity', title: 'Quantité par défaut'},
            {data: 'prefix', title: 'Préfixe'},
            {data: 'color', title: 'Couleur'},
            {data: 'description', title: 'Description'},
            {data: 'mobileSync', title: 'Synchronisation nomade'},
            {data: 'displayed', title: 'Affichage sur les formulaires'},
            {data: 'temperatures', title: 'Températures', orderable: false},
        ],
        rowConfig: {
            needsRowClickAction: true
        },
    };
    return initDataTable('tableNatures', tableNatureConfig);
}
