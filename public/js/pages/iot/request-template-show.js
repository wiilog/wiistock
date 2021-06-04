const REQUEST_TEMPLATE_ID = $(`#request-template-id`).val();
const DISPLAY_WARNING = $(`#request-template-warning`).val();

let table;

$(document).ready(() => {
    table = initPageDatatable();

    InitModal(
        $(`#modalAddLine`),
        $(`#modalAddLine button[type=submit]`),
        Routing.generate(`request_template_line_new`, {requestTemplate: REQUEST_TEMPLATE_ID}),
        {tables: [table]}
    );

    InitModal(
        $(`#modalEditLine`),
        $(`#modalEditLine button[type=submit]`),
        Routing.generate(`request_template_line_edit`, {requestTemplate: REQUEST_TEMPLATE_ID}),
        {tables: [table]}
    );

    InitModal(
        $(`#modalRemoveLine`),
        $(`#modalRemoveLine button[type=submit]`),
        Routing.generate(`request_template_line_remove`, {requestTemplate: REQUEST_TEMPLATE_ID}),
        {tables: [table]}
    );

    let $modalModifyRequestTemplate = $('#modalEditRequestTemplate');
    let $submitModifyRequestTemplate = $('#submitEditRequestTemplate');
    let urlModifyRequestTemplate = Routing.generate('request_template_edit', true);
    InitModal($modalModifyRequestTemplate, $submitModifyRequestTemplate, urlModifyRequestTemplate,
        {
            tables: [table],
            success: () => {window.location.reload()}
        }
    );

    $(`select[name="reference"]`).on(`change`, function() {
        const $select = $(this);
        const data = $select.select2('data')[0];

        if(data) {
            $select.closest(`.modal`).find(`.reference-label`).val(data.label);
        }
    })
})

function initPageDatatable() {
    let tableArticleConfig = {
        processing: true,
        order: [[`reference`, `desc`]],
        ajax: {
            url: Routing.generate(`request_template_article_api`, {requestTemplate: REQUEST_TEMPLATE_ID}),
            type: `POST`
        },
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `reference`, title: `Référence`},
            {data: `label`, title: `Libellé`},
            {data: `location`, title: `Emplacement`},
            {data: `quantity`, title: `Quantité à prélever`},
        ],
        rowConfig: {
            needsRowClickAction: true,
        }
    };

    return initDataTable(`articlesTable`, tableArticleConfig);
}

function displayWarning() {
    return new Promise((resolve, reject) => {
        if(DISPLAY_WARNING) {
            $(`#modalConfirmEdit`).modal(`show`)
                .find(`button[type=submit]`)
                .off(`click`)
                .on(`click`, resolve);
        } else {
            resolve();
        }
    })
}

function openEditRequestTemplateModal($button) {
    const $editModal = $(`#modalEditRequestTemplate`);
    displayWarning().then(() => {
        editRow(
            $button,
            Routing.generate('request_template_edit_api', true),
            $('#modalEditRequestTemplate'),
            $('#submitEditRequestTemplate'),
            true,
            '.delivery-editor-container-edit, .collect-editor-container-edit'
        );

        $editModal.modal(`show`);
    })
}

function openAddArticleModal() {
    const $addLineModal = $(`#modalAddLine`);
    displayWarning().then(() => {
        clearModal($addLineModal);
        $addLineModal.modal(`show`);
    })
}

function openEditArticleModal($button) {
    const $editLineModal = $(`#modalEditLine`);
    displayWarning().then(() => {
        editRow(
            $button,
            Routing.generate('request_template_line_edit_api'),
            $('#modalEditLine'),
            $('#modalEditLine button[type=submit]'),
        );

        $editLineModal.modal(`show`);
    })
}

function openRemoveArticleModal(line) {
    displayWarning().then(() => {
        $.post(Routing.generate('request_template_line_remove', {line}), response => {
            if(response.success) {
                showBSAlert(response.msg, `success`);
                table.ajax.reload();
            }
        });
    })
}
