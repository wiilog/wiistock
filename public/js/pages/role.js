let pathRoles = Routing.generate('role_api', true);
let tableRolesConfig = {
    order: [1, 'asc'],
    ajax:{
        "url": pathRoles,
        "type": "POST"
    },
    columns:[
        { "data": 'Actions', 'title' : '', className: 'noVis', orderable: false},
        { "data": 'Nom', 'title' : 'Nom' },
        { "data": 'Actif', 'title' : 'Actif' },
    ],
    rowConfig: {
        needsRowClickAction: true
    }
};
let tableRoles = initDataTable('tableRoles', tableRolesConfig);

let modalNewRole = $("#modalNewRole");
let submitNewRole = $("#submitNewRole");
let urlNewRole = Routing.generate('role_new', true);
InitModal(modalNewRole, submitNewRole, urlNewRole, {tables: [tableRoles], keepModal: false});

let modalEditRole = $('#modalEditRole');
let submitEditRole = $('#submitEditRole');
let urlEditRole = Routing.generate('role_edit', true);
InitModal(modalEditRole, submitEditRole, urlEditRole, {tables: [tableRoles]});

let ModalDeleteRole = $("#modalDeleteRole");
let SubmitDeleteRole = $("#submitDeleteRole");
let urlDeleteRole = Routing.generate('role_delete', true)
InitModal(ModalDeleteRole, SubmitDeleteRole, urlDeleteRole, {tables: [tableRoles]});
