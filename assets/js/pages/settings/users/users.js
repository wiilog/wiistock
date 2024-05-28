import {formatIconSelector} from "@app/form";
import Routing from '@app/fos-routing';
import {initDataTable} from "@app/datatable";
import {POST} from "@app/ajax";
import Form from "@app/form";

global.editRowUser = editRowUser;

export function initUserPage($container) {
    const tableUser = initDataTable('table-users', {
        processing: true,
        serverSide: true,
        ajax: {
            "url": Routing.generate('user_api', true),
            "type": "POST"
        },
        columns: [
            { "data": 'Actions', 'title': '', orderable: false, className: 'noVis' },
            { "data": "username", 'title': "Nom d'utilisateur" },
            { "data": "email", 'title': 'Email' },
            { "data": "dropzone", 'title': 'Drop zone' },
            { "data": "lastLogin", 'title': 'Dernière connexion' },
            { "data": "role", 'title': 'Rôle' },
            { "data": "visibilityGroup", 'title': 'Groupes de visibilité' },
            { "data": "status", 'title': 'Actif' },
        ],
        rowConfig: {
            needsRowClickAction: true
        },
        drawConfig: {
            needsSearchHide: true,
            needsPagingHide: true,
        },
        order: [['username', 'asc']]
    });

    let $modalNewUser = $("#modalNewUser");
    Form.create($modalNewUser, {clearOnOpen: true})
        .onOpen(() => {
            $modalNewUser.find('.select-all-options').on('click', onSelectAll);
        })
        .submitTo(POST, `user_new`, {tables: [tableUser]});

    const $languageSelect = $('.utilisateur-language');
    $languageSelect.select2({
        minimumResultsForSearch: -1,
        templateResult: formatIconSelector,
        templateSelection: formatIconSelector,
    })

    let $modalEditUser = $("#modalEditUser");
    Form.create($modalEditUser)
        .submitTo(POST, `user_edit`, {tables: [tableUser]});

    let $modalDeleteUser = $("#modalDeleteUser");
    let $submitDeleteUser = $("#submitDeleteUser");
    let pathDeleteUser = Routing.generate('user_delete', true);
    InitModal($modalDeleteUser, $submitDeleteUser, pathDeleteUser, {tables: [tableUser]});

    $container.on(`click`, `.add-secondary-email`, function() {
        const $modal = $(this).closest(`.modal`);

        $modal.find(`.secondary-email.d-none`).first().removeClass(`d-none`);

        if(!$modal.find(`.secondary-email.d-none`).exists()) {
            $(this).addClass(`d-none`)
                .closest(`.form-group`)
                .removeClass(`mb-0`);
        }
    })
}

function editRowUser(button) {
    let path = Routing.generate('user_api_edit', true);
    let modal = $('#modalEditUser');
    let submit = $('#submitEditUser');
    let id = button.data('id');
    let params = {id: id};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        modal.find('.select-all-options').on('click', onSelectAll)
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
        const $languageSelect = $('.utilisateur-language');
        $languageSelect.select2({
            minimumResultsForSearch: -1,
            templateResult: formatIconSelector,
            templateSelection: formatIconSelector,
        })
    }, 'json');

    modal.find(submit).attr('value', id);
}
