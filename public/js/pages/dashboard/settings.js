/**
 * @type {{
 *     index: int
 *     name: string,
 *     rows: {
 *         size: int,
 *         components: {
 *             type: int,
 *             title: string,
 *             index: int,
 *             config: Object.<string,*>,
 *         }[],
 *     }[],
 * }[]}
 */
let dashboards = [];
let cache = [];
let current = null;

const $addRowButton = $('button.add-row-modal-submit');
const $dashboard = $('.dashboard');
const $pagination = $('.dashboard-pagination');
const $dashboardRowSelector = $('.dashboard-row-selector');

$(document).ready(() => {
    dashboards = JSON.parse($(`.dashboards-data`).val());
    recalculateIndexes();
    cacheOriginalDashboard();

    const selected = window.location.hash.replace(`#`, ``);
    if (dashboards[selected - 1] !== undefined) {
        current = dashboards[selected - 1];
    } else if (dashboards.length !== 0) {
        current = dashboards[0];
        window.location.hash = `#1`;
    }

    updateAddRowButton();
    renderDashboard(current);
    renderDashboardPagination();
});

$(window).on("hashchange", function () {
    const dashboard = window.location.hash.replace(`#`, ``);

    if (dashboards[dashboard - 1] !== undefined) {
        current = dashboards[dashboard - 1];

        updateAddRowButton();
        renderDashboard(current);
    } else {
        console.error(`Unknown dashboard "${dashboard}"`);
        window.location.hash = `#1`;
    }
});

$(window).bind('beforeunload', function () {
    return cache !== JSON.stringify(dashboards) ?
        true :
        undefined;
});

$(`.save-dashboards`).click(function () {
    const content = {
        dashboards: JSON.stringify(dashboards)
    };

    $.post(Routing.generate(`save_dashboard_settings`), content)
        .then(function (data) {
            if (data.success) {
                showBSAlert("Dashboards enregistrés avec succès", "success");
                dashboards = JSON.parse(data.dashboards);
                recalculateIndexes();
                cacheOriginalDashboard();

                current = dashboards[current.index];

                updateAddRowButton();
                renderDashboard(current);
                renderDashboardPagination();
            } else {
                throw data;
            }
        })
        .catch(function (error) {
            const date = new Date().toISOString();
            error.responseText = undefined;

            const context = {
                date,
                error,
                dashboards
            };

            $(`[name="error-context"]`).val(JSON.stringify(context));
            $(`#error-modal`).modal(`show`);

            showBSAlert("Une erreur est survenue lors de la sauvegarde des dashboards", "danger");
        });
});

$(`.download-trace`).click(function () {
    const blob = new Blob([$(`[name="error-context"]`).val()]);
    saveAs(blob, `dashboards-error.txt`);
})

$dashboardRowSelector.click(function () {
    const button = $(this);

    $('input[name="new-row-columns-count"]').val(button.data("columns"));
    $dashboardRowSelector.removeClass("selected");
    button.addClass("selected");
    $addRowButton.attr(`disabled`, false);
});

$addRowButton.click(function () {
    const $newRowColumnsCountInput = $('input[name="new-row-columns-count"]');

    const columns = $newRowColumnsCountInput.val();
    $newRowColumnsCountInput.val(``);
    $dashboardRowSelector.removeClass("selected");
    $addRowButton.attr(`disabled`, true);

    if (current !== undefined) {
        current.rows.push({
            index: current.rows.length,
            size: columns,
            components: []
        });

        renderDashboard(current);
        updateAddRowButton();
    }

    $addRowButton.closest('.modal').modal('hide');
});

$('button.add-dashboard-modal-submit').click(function () {
    const $dashboardNameInput = $('input[name="add-dashboard-name"]');
    const name = $dashboardNameInput.val();
    $dashboardNameInput.val(``);

    if (dashboards.length >= 8) {
        console.error("Too many dashboards");
    } else {
        dashboards.push({
            index: dashboards.length,
            name,
            rows: [],
        });

        renderDashboardPagination();
    }
});

$pagination.on(`click`, `[data-target="#rename-dashboard-modal"]`, function () {
    const dashboard = $(this).data(`dashboard`);
    const $indexInput = $(`input[name="rename-dashboard-index"]`);
    const $nameInput = $(`input[name="rename-dashboard-name"]`);

    $indexInput.val(dashboard);
    $nameInput.val(dashboards[dashboard].name);
});

$(`.rename-dashboard-modal-submit`).click(function () {
    const dashboard = $(`input[name="rename-dashboard-index"]`).val();
    const $dashboardNameInput = $('input[name="rename-dashboard-name"]');
    const name = $dashboardNameInput.val();
    $dashboardNameInput.val(``);

    dashboards[dashboard].name = name;
    renderDashboardPagination();
});

$pagination.on(`click`, `.delete-dashboard`, function () {
    const dashboard = Number($(this).data(`dashboard`));

    dashboards.splice(dashboard, 1);
    recalculateIndexes();

    if (dashboard === current.index) {
        current = dashboards[0];
        renderDashboard(current);
        updateAddRowButton();
    }

    renderDashboardPagination();
});

$dashboard.on(`click`, `.delete-row`, function () {
    const $row = $(this).parent();
    const rowIndex = $row.data(`row`);

    $row.remove();
    current.rows.splice(rowIndex, 1);

    recalculateIndexes();
    updateAddRowButton();
});

$dashboard.on(`click`, `.delete-component`, function () {
    const $component = $(this).parent();
    const componentIndex = $component.data(`component`);
    const rowIndex = $component.parents(`.dashboard-row`).data(`row`);

    const row = current.rows[rowIndex];
    delete row.components[componentIndex];

    $(this).parent().replaceWith(renderComponent(componentIndex));
});

function recalculateIndexes() {
    dashboards.forEach((dashboard, i) => {
        dashboard.index = i;

        dashboard.rows.forEach((row, i) => {
            if (dashboard === current) {
                $(`[data-row="${row.index}"]`)
                    .data(`row`, i)
                    .attr(`data-row`, i); //update the dom too
            }

            row.index = i;
        });
    })
}

function cacheOriginalDashboard() {
    cache = JSON.stringify(dashboards);
}

function renderDashboard(dashboard) {
    if (dashboard === undefined)
        return;

    $dashboard.empty();

    dashboard.rows
        .map(renderRow)
        .forEach(row => $dashboard.append(row));
}

function updateAddRowButton() {
    if (current !== undefined) {
        $(`[data-target="#add-row-modal"]`)
            .prop(`disabled`, current.rows.length >= 6);
    }
}

function renderRow(row) {
    const $row = $(`<div class="dashboard-row" data-row="${row.index}"></div>`);

    for (let i = 0; i < row.size; ++i) {
        $row.append(renderComponent(row.components[i] !== undefined ? row.components[i] : i));
    }

    $row.append(`
        <button class="btn btn-danger btn-sm delete-row ml-1">
            <i class="fa fa-trash"></i>
        </button>
    `);

    return $row;
}

function renderComponent(component) {
    let $component;

    if (!Number.isInteger(component)) {
        const content = ``; //TODO: call proper render function

        $component = $(`
            <div class="dashboard-component" data-component="${component.index}">
                <span class="title">${component.title}</span>
                <div class="component-content">${content}</div>
                <button class="btn btn-danger btn-sm delete-component">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        `);
    } else {
        $component = $(`
            <div class="dashboard-component empty" data-component="${component}">
                <button class="btn btn-primary btn-ripple btn-sm" data-toggle="modal" data-target="#add-component-modal">
                    <i class="fas fa-plus mr-2"></i> Ajouter un composant<br>
                </button>
            </div>
        `);
    }

    return $component;
}

function renderDashboardPagination() {
    $('.dashboard-pagination > div, .external-dashboards > a').remove();

    dashboards
        .map(dashboard => createDashboardSelectorItem(dashboard))
        .forEach(item => item.insertBefore(".dashboard-pagination > button"));

    dashboards
        .map(dashboard => createExternalDashboardLink(dashboard))
        .forEach(item => $('.external-dashboards').append(item));

    $('[data-target="#add-dashboard-modal"]')
        .attr(`disabled`, dashboards.length >= 8);
}

function createDashboardSelectorItem(dashboard) {
    const number = dashboard.index + 1;

    let name;
    if (dashboard.name.length >= 20) {
        name = $.trim(dashboard.name).substring(0, 17) + "...";
    } else {
        name = dashboard.name;
    }

    return $(`
        <div class="d-flex align-items-center mr-3">
            <a href="#${number}" title="${dashboard.name}">${name}</a>
            <div class="dropdown dropright ml-1">
                <span class="badge badge-primary square-sm pointer" data-toggle="dropdown">
                    <i class="fas fa-pen"></i>
                </span>

                <div class="dropdown-menu dropdown-follow-gt pointer">
                    <a class="dropdown-item rename-dashboard" role="button" data-dashboard="${dashboard.index}"
                         data-toggle="modal" data-target="#rename-dashboard-modal">
                        <i class="fas fa-edit mr-2"></i>Renommer
                    </a>
                    <a class="dropdown-item delete-dashboard" role="button" data-dashboard="${dashboard.index}">
                        <i class="fas fa-trash mr-2"></i>Supprimer
                    </a>
                </div>
            </div>
        </div>
    `);
}

function createExternalDashboardLink(dashboard) {
    return $(`
        <a class="dropdown-item">
            <i class="fas fa-external-link-alt"></i> Dashboard "${dashboard.name}"
        </a>
    `);
}
