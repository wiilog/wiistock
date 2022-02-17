export function initializeRolesPage() {
    const tableRoles = initDataTable('tableRoles', {
        order: [['name', 'asc']],
        ajax:{
            "url": Routing.generate('settings_role_api', true),
            "type": "POST"
        },
        columns:[
            { data: 'actions', title : '', className: 'noVis', orderable: false, width: '10px'},
            { data: 'name', title: 'Nom'},
            { data: 'quantityType', title : 'Ajout quantité' },
            { data: 'isMailSendAccountCreation', title : 'Réception email création nouveau compte' },
        ],
        rowConfig: {
            needsRowClickAction: true
        },
        drawConfig: {
            needsSearchHide: true,
            needsPagingHide: true,
            needsSearchOverride: true,
        },
    });

    let $modalDeleteRole = $("#modalDeleteRole");
    InitModal(
        $modalDeleteRole,
        $modalDeleteRole.find('.submit-button'),
        Routing.generate('settings_role_delete', true),
        {tables: [tableRoles]}
    );
}
