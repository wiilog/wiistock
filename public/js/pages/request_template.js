$('.select2').select2();
let pathEmplacement = Routing.generate(`request_template_api`, true);
const TABLE_CONFIG = {
    processing: true,
    serverSide: true,
    order: [[`name`, `desc`]],
    ajax: {
        url: pathEmplacement,
        type: "POST",
    },
    drawConfig: {
        needsEmplacementSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    columns: [
        {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
        {data: 'name', name: 'name', title: 'Nom'},
        {data: 'type', name: 'type', title: 'Type'},
    ]
};

const table = initDataTable(`tableRequestTemplate`, TABLE_CONFIG);

let $modalNewRequestTemplate = $("#modalNewRequestTemplate");
let $submitNewRequestTemplate = $("#submitNewRequestTemplate");
let urlNewRequestTemplate = Routing.generate('request_template_new', true);
InitModal($modalNewRequestTemplate, $submitNewRequestTemplate, urlNewRequestTemplate, {tables: [table]});

let $modalModifyRequestTemplate = $('#modalEditRequestTemplate');
let $submitModifyRequestTemplate = $('#submitEditRequestTemplate');
let urlModifyRequestTemplate = Routing.generate('request_template_edit', true);
InitModal($modalModifyRequestTemplate, $submitModifyRequestTemplate, urlModifyRequestTemplate, {tables: [table]});

let modalDeleteRequestTemplate = $('#modalDeleteRequestTemplate');
let submitDeleteRequestTemplate = $('#submitDeleteRequestTemplate');
let urlDeleteRequestTemplate = Routing.generate('request_template_delete', true);
InitModal(modalDeleteRequestTemplate, submitDeleteRequestTemplate, urlDeleteRequestTemplate, {tables: [table]});
