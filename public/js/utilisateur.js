let urlUtilisateur = Routing.generate('user_api', true);
let tableUser = $('#tableUser_id').DataTable({
    processing: true,
    serverSide: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": urlUtilisateur,
        "type": "POST"
    },
    columns: [
        { "data": 'Actions', 'title': '', orderable: false, className: 'noVis' },
        { "data": "Nom d'utilisateur", 'title': "Nom d'utilisateur" },
        { "data": "Email", 'title': 'Email' },
        { "data": "Dropzone", 'title': 'Drop zone' },
        { "data": "Dernière connexion", 'title': 'Dernière connexion' },
        { "data": "Rôle", 'title': 'Rôle', orderable: false, className: 'noVis' },
    ],
    rowCallback: function(row, data) {
        initActionOnRow(row);
    },
    order: [[1, 'asc']]
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

$(function() {
    $('.select2').select2();
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
})

function editRowUser(button) {
    let path = Routing.generate('user_api_edit', true);
    let modal = $('#modalEditUser');
    let submit = $('#submitEditUser');
    let id = button.data('id');
    let params = {id: id};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        modal.find('#inputEditType').val(data.userTypes).select2();
        ajaxAutoCompleteEmplacementInit($('#dropzone'));
        if (data.dropzone) {
            let newOption = new Option(data.dropzone.text, data.dropzone.id, true, true);
            modal.find('#dropzone').append(newOption).trigger('change');
        }
    }, 'json');

    modal.find(submit).attr('value', id);
}
