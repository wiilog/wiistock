/**
 * @type {{
 *     index: int
 *     name: string,
 *     rows: {
 *         rowSize: int,
 *         components: {
 *             type: int,
 *             title: string,
 *             index: int,
 *             config: Object.<string,*>,
 *         }[],
 *     }[],
 * }[]}
 */
const dashboards = [];
let current = null;

$(document).ready(() => {
    loadSampleData().then(() => {
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
    })
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

$('.dashboard-row-selector').click(function () {
    const button = $(this);

    $('input[name="new-row-columns-count"]').val(button.data("columns"));
    $(".dashboard-row-selector").removeClass("selected");
    button.addClass("selected");
    $('button.add-row-modal-submit').attr(`disabled`, false);
});

$('button.add-row-modal-submit').click(function () {
    const $newRowColumnsCountInput = $('input[name="new-row-columns-count"]');

    const columns = $newRowColumnsCountInput.val();
    $newRowColumnsCountInput.val(``);
    $(".dashboard-row-selector").removeClass("selected");
    $('button.add-row-modal-submit').attr(`disabled`, true);

    if (current !== undefined) {
        current.rows.push({
            rowSize: columns,
            components: []
        });

        renderDashboard(current);
        updateAddRowButton();
    }
});

$('button.add-dashboard-modal-submit').click(function () {
    const $dashboardNameInput = $('input[name="dashboard-name"]');
    const name = $dashboardNameInput.val();
    $dashboardNameInput.val(``);

    if(dashboards.length >= 8) {
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

$('.dashboard-pagination').on(`click`, `.delete-dashboard`, function () {
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

$('.dashboard').on(`click`, `.delete-row`, function () {
    const $row = $(this).parent();
    const rowIndex = $row.data(`row`);

    $row.remove();
    current.rows.splice(rowIndex, 1);

    recalculateIndexes();
    updateAddRowButton();
});

$('.dashboard').on(`click`, `.delete-component`, function () {
    const $component = $(this).parent();
    const componentIndex = $component.data(`component`);
    const rowIndex = $component.parents(`.dashboard-row`).data(`row`);

    const row = current.rows[rowIndex];
    delete row.components[componentIndex];

    $(this).parent().replaceWith(renderComponent(componentIndex));
});

function loadSampleData() {
    dashboards.push({
        index: 0,
        name: "Test de dashboard",
        rows: [
            {
                index: 0,
                rowSize: 2,
                components: [
                    {
                        type: 0,
                        title: "Hello",
                        index: 0,
                        config: {}
                    },
                    {
                        type: 0,
                        title: "Hello",
                        index: 1,
                        config: {}
                    }
                ]
            },
            {
                index: 1,
                rowSize: 1,
                components: [
                    {
                        type: 0,
                        title: "Hello",
                        index: 0,
                        config: {}
                    }
                ]
            },
            {
                index: 2,
                rowSize: 5,
                components: [
                    {
                        index: 0,
                        type: 0,
                        title: "Hello",
                        config: {}
                    },
                    undefined,
                    {
                        index: 2,
                        type: 0,
                        title: "Hello",
                        config: {}
                    },
                    undefined,
                    {
                        index: 4,
                        type: 0,
                        title: "Hello",
                        config: {}
                    }
                ]
            },
            {
                index: 3,
                rowSize: 4,
                components: [
                    undefined,
                    undefined,
                    undefined,
                    undefined,
                ]
            },
            {
                index: 4,
                rowSize: 6,
                components: [
                    undefined,
                    {
                        type: 0,
                        title: "Hello",
                        index: 1,
                        config: {}
                    },
                    undefined,
                    undefined,
                    undefined,
                    undefined,
                ]
            }
        ]
    });

    dashboards.push({
        index: 1,
        name: "Autre test",
        rows: [
            {
                index: 0,
                rowSize: 3,
                components: [
                    {
                        type: 0,
                        title: "Hello",
                        index: 0,
                        config: {}
                    },
                    {
                        type: 0,
                        title: "Hello",
                        index: 1,
                        config: {}
                    },
                    {
                        type: 0,
                        title: "Hello",
                        index: 2,
                        config: {}
                    }
                ]
            },
            {
                index: 1,
                rowSize: 6,
                components: [
                    undefined,
                    {
                        index: 1,
                        type: 0,
                        title: "Hello",
                        config: {}
                    },
                    undefined,
                    undefined,
                    undefined,
                    {
                        index: 5,
                        type: 0,
                        title: "Hello",
                        config: {}
                    },
                ]
            },
            {
                index: 2,
                rowSize: 4,
                components: [
                    {
                        index: 0,
                        type: 0,
                        title: "Hello",
                        config: {}
                    },
                    {
                        index: 1,
                        type: 0,
                        title: "Hello",
                        config: {}
                    },
                    {
                        index: 2,
                        type: 0,
                        title: "Hello",
                        config: {}
                    },
                    undefined,
                ]
            }
        ]
    });

    return Promise.resolve();
}

function recalculateIndexes() {
    dashboards.forEach((dashboard, i) => {
        dashboard.index = i;

        dashboard.rows.forEach((row, i) => {
            if(dashboard === current) {
                $(`[data-row="${row.index}"]`)
                    .data(`row`, i)
                    .attr(`data-row`, i); //update the dom too
            }

            row.index = i;
        });
    })
}

function renderDashboard(dashboard) {
    if (dashboard === undefined)
        return;

    const container = $('.dashboard');
    container.empty();

    dashboard.rows
        .map(renderRow)
        .forEach(row => container.append(row));
}

function updateAddRowButton() {
    if (current !== undefined) {
        $(`[data-target="#add-row-modal"]`)
            .prop(`disabled`, current.rows.length >= 6);
    }
}

function renderRow(row) {
    const $row = $(`<div class="dashboard-row" data-row="${row.index}"></div>`);

    for (let i = 0; i < row.rowSize; ++i) {
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
    if(dashboard.name.length >= 20) {
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
                    <a class="dropdown-item rename-dashboard" role="button" data-dashboard="${dashboard.index}">
                        <i class="fas fa-edit"></i> Renommer
                    </a>
                    <a class="dropdown-item delete-dashboard" role="button" data-dashboard="${dashboard.index}">
                        <i class="fas fa-trash"></i> Supprimer
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
