$('.select2').select2();

let urlUtilisateur = Routing.generate('user_api', true);
let tableUser = $('#tableUser_id').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": urlUtilisateur,
        "type": "POST"
    },
    columns: [
        { "data": "Nom d'utilisateur", 'title': "Nom d'utilisateur" },
        { "data": "Email", 'title': 'Email' },
        { "data": "Dernière connexion", 'title': 'Dernière connexion' },
        { "data": "Rôle", 'title': 'Rôle' },
        { "data": 'Actions', 'title': 'Actions' },
    ],
});

let modalNewUser = $("#modalNewUser");
let submitNewUser = $("#submitNewUser");
let pathNewUser = Routing.generate('user_new', true);
InitialiserModal(modalNewUser, submitNewUser, pathNewUser, tableUser, displayErrorUser, false);

let modalEditUser = $("#modalEditUser");
let submitEditUser = $("#submitEditUser");
let pathEditUser = Routing.generate('user_edit', true);
InitialiserModal(modalEditUser, submitEditUser, pathEditUser, tableUser, displayErrorUser, false);

let modalDeleteUser = $("#modalDeleteUser");
let submitDeleteUser = $("#submitDeleteUser");
let pathDeleteUser = Routing.generate('user_delete', true);
InitialiserModal(modalDeleteUser, submitDeleteUser, pathDeleteUser, tableUser);

function editRole(select) {
    let params = JSON.stringify({
        'role': select.val(),
        'userId': select.data('user-id')
    });

    $.post(Routing.generate('user_edit_role'), params, 'json');
}

function displayErrorUser(data) {
    let modal = data.action === 'new' ? modalNewUser : modalEditUser;
    displayError(modal, data.msg, data.success);
}