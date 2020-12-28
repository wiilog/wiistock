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
const dashboards = [];
let current = null;

const $addRowButton = $('button.add-row-modal-submit');
const $dashboard = $('.dashboard');
const $pagination = $('.dashboard-pagination');
const $dashboardRowSelector = $('.dashboard-row-selector');const $modalComponentTypeFirstStep = $('#modalComponentTypeFistStep');
const $modalComponentTypeSecondStep = $('#modalComponentTypeSecondStep');

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

function loadSampleData() {
    dashboards.push({
        index: 0,
        name: "Test de dashboard",
        rows: [
            {
                index: 0,
                size: 2,
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
                size: 1,
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
                size: 5,
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
                size: 4,
                components: [
                    undefined,
                    undefined,
                    undefined,
                    undefined,
                ]
            },
            {
                index: 4,
                size: 6,
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
                size: 3,
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
                size: 6,
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
                size: 4,
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
            if (dashboard === current) {
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

    for (let componentIndex = 0; componentIndex < row.size; ++componentIndex) {
        $row.append(renderComponent(row.components[componentIndex] || componentIndex));
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
        $component = $('<div/>', {
            class: 'dashboard-component empty',
            'data-component': component,
            html: $('<div/>', {
                class: 'btn btn-primary btn-ripple btn-sm',
                click: openModalComponentTypeFirstStep,
                html: `<i class="fas fa-plus mr-2"></i> Ajouter un composant`
            })
        });
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

function openModalComponentTypeFirstStep() {
    $modalComponentTypeFirstStep.modal('show');

    const $button = $(this);
    const $component = $button.closest('.dashboard-component');
    const $row = $component.closest('.dashboard-row');

    $modalComponentTypeFirstStep
        .find('input[name="componentIndex"]')
        .val($component.data('component'));

    $modalComponentTypeFirstStep
        .find('input[name="rowIndex"]')
        .val($row.data('row'));
}

function openModalComponentTypeNextStep($button) {
    const firstStepIsShown = $modalComponentTypeFirstStep.hasClass('show');
    if (firstStepIsShown) {
        const componentTypeId = $button.data('component-type-id');
        const componentTypeName = $button.data('component-type-name');
        const $form = $button.closest('.form');
        const apiRoute = Routing.generate('dashboard_component_type_form', {componentType: componentTypeId});
        const rowIndex = $form.find('[name="rowIndex"]').val();
        const componentIndex = $form.find('[name="componentIndex"]').val();

        const component = {};

        wrapLoadingOnActionButton($button, () => $.post(
            apiRoute,
            {
                rowIndex,
                componentIndex,
                values: JSON.stringify({
                    title: component.title,
                    config: component.config
                })
            },
            function (data) {
                if(data.html) {
                    initSecondStep(data.html);
                } else {
                    editComponent(rowIndex, componentIndex, {
                        config: [],
                        title: componentTypeName,
                        componentType: componentTypeId
                    })
                }
                $modalComponentTypeFirstStep.modal('hide');
                if(data.html) {
                    $modalComponentTypeSecondStep.modal('show');
                }
            },
            'json'
        ), true);
    }
}

function onComponentTypeSaved($modal) {
    clearFormErrors($modal);
    const {success, errorMessages, $isInvalidElements, data} = ProcessForm($modal);

    if (success) {
        const {rowIndex, componentIndex, componentType, title, ...config} = data;
        editComponent(rowIndex, componentIndex, {title, config, componentType});

        $modalComponentTypeSecondStep.modal('hide');
    }
    else {
        displayFormErrors($modal, {
            $isInvalidElements,
            errorMessages
        });
    }
}

function editComponent(rowIndex, componentIndex, {title, config, componentType}) {
    const currentRow = getCurrentDashboardRow(rowIndex);
    if (currentRow && componentIndex < currentRow.size) {
        let currentComponent = getRowComponent(currentRow, componentIndex);
        if (!currentComponent) {
            currentComponent = {index: componentIndex};
            currentRow.components[componentIndex] = currentComponent;
        }
        currentComponent.config = config;
        currentComponent.title = title;
        currentComponent.type = componentType;

        const $currentComponent = $dashboard
            .find(`.dashboard-row[data-row="${rowIndex}"]`)
            .find(`.dashboard-component[data-component="${componentIndex}"]`);
        $currentComponent.replaceWith(renderComponent(currentComponent));
    }
}

function initSecondStep(html) {
    const $modalComponentTypeSecondStepContent = $modalComponentTypeSecondStep.find('.content');
    $modalComponentTypeSecondStepContent.html('');
    $modalComponentTypeSecondStepContent.html(html);

    Select2.location($modalComponentTypeSecondStep.find('.ajax-autocomplete-location'));
    Select2.carrier($modalComponentTypeSecondStep.find('.ajax-autocomplete-transporteur'));

    const $submitButton = $modalComponentTypeSecondStep.find('button[type="submit"]');
    $submitButton.off('click');
    $submitButton.on('click', () => onComponentTypeSaved($modalComponentTypeSecondStep));
}

function getRowComponent(row, componentIndex) {
    // noinspection EqualityComparisonWithCoercionJS
    return (row && componentIndex < row.size)
        ? row.components.find(({index} = {}) => (index == componentIndex))
        : undefined;
}

function getCurrentDashboardRow(rowIndex) {
    // noinspection EqualityComparisonWithCoercionJS
    return current.rows.find(({index}) => (index == rowIndex));
}
