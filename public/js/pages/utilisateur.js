let urlUtilisateur = Routing.generate('user_api', true);
let tableUserConfig = {
    processing: true,
    serverSide: true,
    ajax: {
        "url": urlUtilisateur,
        "type": "POST"
    },
    columns: [
        {data: `Actions`, title: ``, orderable: false, className: `noVis`},
        {data: `username`, title: `Nom d'utilisateur`},
        {data: `email`, title: `Email`},
        {data: `dropzone`, title: `Drop zone`},
        {data: `lastLogin`, title: `Dernière connexion`},
        {data: `role`, title: `Rôle`},
        {data: `visibilityGroup`, title: `Groupes de visibilité`},
        {data: `status`, title: `Actif`},
    ],
    rowConfig: {
        needsRowClickAction: true
    },
    order: [['username', 'ASC']]
};
let tableUser = initDataTable('tableUser_id', tableUserConfig);

let modalNewUser = $("#modalNewUser");
let submitNewUser = $("#submitNewUser");
let pathNewUser = Routing.generate('user_new', true);
InitModal(modalNewUser, submitNewUser, pathNewUser, {tables: [tableUser]});

let modalEditUser = $("#modalEditUser");
let submitEditUser = $("#submitEditUser");
let pathEditUser = Routing.generate('user_edit', true);
InitModal(modalEditUser, submitEditUser, pathEditUser, {tables: [tableUser]});

let modalDeleteUser = $("#modalDeleteUser");
let submitDeleteUser = $("#submitDeleteUser");
let pathDeleteUser = Routing.generate('user_delete', true);
InitModal(modalDeleteUser, submitDeleteUser, pathDeleteUser, {tables: [tableUser]});

$(function() {
    $('.select2-old').select2();
    Select2Old.location($('.ajax-autocomplete-location-edit'));
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
        modal.find('[name="deliveryTypes"]').val(data.userDeliveryTypes).select2();
        modal.find('[name="dispatchTypes"]').val(data.userDispatchTypes).select2();
        modal.find('[name="handlingTypes"]').val(data.userHandlingTypes).select2();
        Select2Old.location($('#dropzone'));
        if (data.dropzone) {
            let newOption = new Option(data.dropzone.text, data.dropzone.id, true, true);
            modal.find('#dropzone').append(newOption).trigger('change');
        }
        if (data.visibilityGroups && modal.find('#visibility-group').find('option').length === 0) {
            data.visibilityGroups.forEach((vg) => {
                let newOption = new Option(vg.text, vg.id, true, true);
                modal.find('#visibility-group').append(newOption).trigger('change');
            });
        }
    }, 'json');

    modal.find(submit).attr('value', id);
}
