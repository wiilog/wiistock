$('.select2').select2();

let urlUtilisateur = Routing.generate('user_api', true);
let tableUser = $('#tableUser_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": urlUtilisateur,
        "type": "POST"
    },
    columns: [
        { "data": "Nom d'utilisateur" },
        { "data": "Email" },
        { "data": "Dernière connexion" },
        { "data": "Rôle" },
        { "data": 'Actions' },
    ],
});

let modalNewUser = $("#modalNewUser");
let submitNewUser = $("#submitNewUser");
let pathNewUser = Routing.generate('user_new', true);
InitialiserModal(modalNewUser, submitNewUser, pathNewUser, tableUser, alertErrorMsg);

let modalEditUser = $("#modalEditUser");
let submitEditUser = $("#submitEditUser");
let pathEditUser = Routing.generate('user_edit', true);
InitialiserModal(modalEditUser, submitEditUser, pathEditUser, tableUser, alertErrorMsg);

let modalDeleteUser = $("#modalDeleteUser");
let submitDeleteUser = $("#submitDeleteUser");
let pathDeleteUser = Routing.generate('user_delete', true);
InitialiserModal(modalDeleteUser, submitDeleteUser, pathDeleteUser, tableUser);

function alertErrorMsg(data) {
    if (data !== true) {
        alert(data); //TODO gérer erreur retour plus propre (alert bootstrap)
    }
}

function editRole(select) {
    let params = JSON.stringify({
        'role': select.val(),
        'userId': select.data('user-id')
        });

    $.post(Routing.generate('user_edit_role'), params, 'json');
}