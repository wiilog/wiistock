$(function() {
    const projectTable = initProjectTable();

    const $modalNewProject = $(`#modalNewProject`);
    const $submitNewProject = $modalNewProject.find(`button.submit`);
    const urlNewProject = Routing.generate(`project_new`, true);
    InitModal($modalNewProject, $submitNewProject, urlNewProject, {tables: [projectTable]});

    const $modalEditProject = $(`#modalEditProject`);
    const $submitEditProject = $(`#submitEditProject`);
    const urlEditProject = Routing.generate(`project_edit`, true);
    InitModal($modalEditProject, $submitEditProject, urlEditProject, {tables: [projectTable]});

    const $modalDeleteProject = $(`#modalDeleteProject`);
    const $submitDeleteProject = $(`#submitDeleteProject`);
    const urlDeleteProject = Routing.generate(`project_delete`, true);
    InitModal($modalDeleteProject, $submitDeleteProject, urlDeleteProject, {tables: [projectTable]});
});

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
