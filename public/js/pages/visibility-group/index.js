$(function() {

    const groupsTableConfig = {
        responsive: true,
        serverSide: true,
        processing: true,
        order: [
            ['label', "ASC"]
        ],
        ajax: {
            url: Routing.generate('visibility_group_api', true),
            type: "POST",
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        columns: [
            {data: `actions`, name: `actions`, title: '', className: 'noVis', orderable: false, width: `10px`},
            {data: `label`, title: `Libellé`},
            {data: `description`, title: `Description`},
            {data: `status`, title: `Statut`},
        ],
    };
    let visibilityGroupTable = initDataTable($('#tableVisibilityGroup'), groupsTableConfig);

    let modalNewVisibilityGroup = $("#modalNewVisibilityGroup");
    let submitNewVisibilityGroup = $("#submitNewVisibilityGroup");
    let urlNewVisibilityGroup = Routing.generate('visibility_group_new', true);
    InitModal(modalNewVisibilityGroup, submitNewVisibilityGroup, urlNewVisibilityGroup, {tables: [visibilityGroupTable]});

    let ModalDeleteVisibilityGroup = $("#modalDeleteVisibilityGroup");
    let SubmitDeleteVisibilityGroup = $("#submitDeleteVisibilityGroup");
    let urlDeleteVisibilityGroup = Routing.generate('visibility_group_delete', true)
    InitModal(ModalDeleteVisibilityGroup, SubmitDeleteVisibilityGroup, urlDeleteVisibilityGroup, {tables: [visibilityGroupTable]});

    let modalModifyVisibilityGroup = $('#modalEditVisibilityGroup');
    let submitModifyVisibilityGroup = $('#submitEditVisibilityGroup');
    let urlModifyVisibilityGroup = Routing.generate('visibility_group_edit', true);
    InitModal(modalModifyVisibilityGroup, submitModifyVisibilityGroup, urlModifyVisibilityGroup, {tables: [visibilityGroupTable]});
});
