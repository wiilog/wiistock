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
        columns: [
            {data: `actions`, name: `actions`, title: '', className: 'noVis', orderable: false, width: `10px`},
            {data: `label`, title: `Libellé`},
            {data: `description`, title: `Description`},
            {data: `status`, title: `Statut`},
        ],
    };
    initDataTable($('#tableVisibilityGroup'), groupsTableConfig);
});
