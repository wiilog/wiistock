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

$(document).ready(() => {
    initEditor('.handling-editor-container');
    initEditor('.delivery-editor-container');
    initEditor('.collect-editor-container');

    const $modal = $(`#modalNewRequestTemplate`);
    const $forms = {
        1: $modal.find(`.handling-form`),
        2: $modal.find(`.delivery-form`),
        3: $modal.find(`.collect-form`),
    };

    $(`.type-selector`).on(`change`, function() {
        const $select = $(this);

        const value = Number($select.val());
        let $selected = $forms[value];

        $modal.find(`.sub-form`).addClass(`d-none`);
        $modal.find(`.data:not(.always-visible)`)
            .addClass(`hidden-data`)
            .removeClass(`data`);

        if($selected) {
            $selected.removeClass(`d-none`);
            $selected.find(`.hidden-data`)
                .addClass(`data`)
                .removeClass(`hidden-data`);
        }
    })
})
