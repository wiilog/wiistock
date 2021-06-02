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
    initEditor(' .editor-container');

    $(`.type-selector`).on(`change`, function() {
        const $select = $(this);
        const $modal = $select.parents(`.modal`);

        $modal.find(`.sub-form`).addClass(`d-none`);

        const value = Number($select.val());
        if(value === 1) {
            $modal.find(`.handling-form`).removeClass(`d-none`);
        } else if(value === 2) {
            $modal.find(`.delivery-form`).removeClass(`d-none`);
        } else if(value === 3) {
            $modal.find(`.collect-form`).removeClass(`d-none`);
        }
    })
})
