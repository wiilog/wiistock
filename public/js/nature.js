let pathNature = Routing.generate('nature_param_api', true);
let tableNatureConfig = {
    ajax: {
        "url": pathNature,
        "type": "POST"
    },
    columnDefs: [
        {
            orderable: false,
            targets: 0
        }
    ],
    order: [1, 'asc'],
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis'},
        {"data": 'Label', 'title': 'Libellé'},
        {"data": 'Code', 'title': 'Code'},
        {"data": 'Quantité par défaut', 'title': 'Quantité par défaut'},
        {"data": 'Préfixe', 'title': 'Préfixe'},
        {"data": 'Couleur', 'title': 'Couleur'},
        {"data": 'description', 'title': 'Description'},
        {"data": 'mobileSync', 'title': 'Synchronisation nomade'},
    ],
    rowConfig: {
        needsRowClickAction: true
    },
};
let tableNature = initDataTable('tableNatures', tableNatureConfig);

let modalNewNature = $("#modalNewNature");
let submitNewNature = $("#submitNewNature");
let urlNewNature = Routing.generate('nature_new', true);
InitModal(modalNewNature, submitNewNature, urlNewNature, {tables: [tableNature]});

let modalEditNature = $('#modalEditNature');
let submitEditNature = $('#submitEditNature');
let urlEditNature = Routing.generate('nature_edit', true);
InitModal(modalEditNature, submitEditNature, urlEditNature, {tables: [tableNature]});

let modalDeleteNature = $("#modalDeleteNature");
let submitDeleteNature = $("#submitDeleteNature");
let urlDeleteNature = Routing.generate('nature_delete', true)
InitModal(modalDeleteNature, submitDeleteNature, urlDeleteNature, {tables: [tableNature]});
