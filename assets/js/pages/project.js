import Form from "@app/form";
import AJAX, {POST} from "@app/ajax";

$(function() {
    const projectTable = initProjectTable();
    initializeNewModal(projectTable);
    initializeEditModal(projectTable);
    initializeDeleteModal(projectTable);

});

function initializeNewModal(table) {
    Form.create(`#modalNewProject`, {clearOnOpen: true})
        .submitTo(`POST`, `project_new`, {
            table
        })
}

function initializeEditModal(table) {
    Form.create(`#modalEditProject`).submitTo(`POST`, `project_edit`, {
        table
    })
}

function initializeDeleteModal(table) {
    Form.create(`#modalDeleteProject`).submitTo(`POST`, `project_delete`, {
        table
    })
}

function initProjectTable() {
    return initDataTable(`projectTable_id`, {
        processing: true,
        serverSide: true,
        paging: true,
        order: [[`code`, `desc`]],
        ajax: {
            url: Routing.generate(`project_api`, true),
            type: `POST`
        },
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `code`, title: `Code`},
            {data: `description`, title: `Description`},
            {data: `projectManager`, title: `Chef de projet`},
            {data: `active`, title: `Actif`, orderable: false},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        drawConfig: {
            needsSearchOverride: true
        }
    });
}
