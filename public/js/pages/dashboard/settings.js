const MAX_NUMBER_ROWS = 6;
const MAX_NUMBER_PAGES = 8;

/**
 * @type {{
 *     index: int
 *     name: string,
 *     updated: boolean,
 *     rows: {
 *         size: int,
 *         updated: boolean,
 *         components: {
 *             updated: boolean,
 *             type: int,
 *             meterKey: string,
 *             index: int,
 *             config: Object.<string,*>,
 *         }[],
 *     }[],
 * }[]}
 */
let dashboards = [];
let current = null;
let somePagesDeleted = false;

const $addRowButton = $('button.add-row-modal-submit');
const $dashboard = $('.dashboard');
const $pagination = $('.dashboard-pagination');
const $dashboardRowSelector = $('.dashboard-row-selector');
const $modalComponentTypeFirstStep = $('#modalComponentTypeFistStep');
const $modalComponentTypeSecondStep = $('#modalComponentTypeSecondStep');

$(document).ready(() => {
    dashboards = JSON.parse($(`.dashboards-data`).val());
    loadCurrentDashboard(true);

    $(window).on("hashchange", loadCurrentDashboard);

    $(`.save-dashboards`).on('click', function () {
        wrapLoadingOnActionButton($(this), onDashboardSaved);
    });

    $addRowButton.on('click', onRowAdded);
    $dashboard.on(`click`, `.delete-row`, onRowDeleted);

    $dashboard.on(`click`, `.edit-component`, onComponentEdited);
    $dashboard.on(`click`, `.delete-component`, onComponentDeleted);

    $('button.add-dashboard-modal-submit').on('click', onPageAdded);
    $pagination.on(`click`, `.delete-dashboard`, onPageDeleted);

    $(window).bind('beforeunload', hasEditDashboard);
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
    const $modal = $dashboardNameInput.closest('.modal');
    if(name) {
        $dashboardNameInput.val(``);

        if (dashboards[dashboard].name !== name) {
            dashboards[dashboard].name = name;
            dashboards[dashboard].updated = true;
        }
        renderDashboardPagination();
        $modal.modal('hide');
    }
    else {
        showBSAlert("Veuillez renseigner un nom de dashboard.", "danger");
    }
});


function recalculateIndexes() {
    dashboards.forEach((dashboard, dashboardIndex) => {
        dashboard.index = dashboardIndex;

        (dashboard.rows || []).forEach((row, rowIndex) => {
            if (dashboard === current) {
                $(`[data-row="${row.index}"]`)
                    .data(`row`, rowIndex)
                    .attr(`data-row`, rowIndex); //update the dom too
            }

            row.index = rowIndex;
        });
    });
}

function renderCurrentDashboard() {
    $dashboard.empty();
    if (current) {
        current.rows
            .map(renderRow)
            .forEach(row => $dashboard.append(row));
    }
}

function updateAddRowButton() {
    $(`[data-target="#add-row-modal"]`)
        .prop(`disabled`, !current || current.rows.length >= MAX_NUMBER_ROWS);
    $('[name="external-display"], .save-dashboards').prop(`disabled`, !current || current.rows.length === 0);
}

function renderRow(row) {
    const $row = $(`<div class="dashboard-row" data-row="${row.index}"></div>`);

    for (let componentIndex = 0; componentIndex < row.size; ++componentIndex) {
        const component = getRowComponent(row, componentIndex);
        $row.append(renderConfigComponent(component || componentIndex, true));
    }

    $row.append(`
        <button class="btn btn-danger btn-sm delete-row ml-1">
            <i class="fa fa-trash"></i>
        </button>
    `);

    return $row;
}

function renderConfigComponent(component, init = false) {
    let $componentContainer;

    if (component && typeof component === 'object') {
        $componentContainer = $('<div/>', {
            class: 'dashboard-component',
            'data-component': component.index
        });

        $componentContainer.pushLoader('black', 'normal');
        renderComponentExample(
            $componentContainer,
            component.type,
            component.meterKey,
            component.config || {},
            init ? component.initData : undefined
        )
            .then(() => {
                $componentContainer.append($(`
                    <div class="component-toolbox">
                        <button class="btn btn-primary btn-sm edit-component m-1 ${component.template === null ? 'd-none' : ''}">
                            <i class="fa fa-pen"></i>
                        </button>
                        <button class="btn btn-danger btn-sm delete-component m-1">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                `));
            });
    } else {
        $componentContainer = $('<div/>', {
            class: 'dashboard-component empty',
            'data-component': component,
            html: $('<div/>', {
                class: 'btn btn-primary btn-ripple btn-sm',
                click: openModalComponentTypeFirstStep,
                html: `<i class="fas fa-plus mr-2"></i> Ajouter un composant`
            })
        });
    }

    return $componentContainer;
}

function renderDashboardPagination() {
    $('.dashboard-pagination > div, .external-dashboards > a').remove();

    dashboards
        .map((dashboard) => createDashboardSelectorItem(dashboard))
        .forEach(($item) => {
            $item.insertBefore(".dashboard-pagination > button")
        });

    dashboards
        .map((dashboard) => createExternalDashboardLink(dashboard))
        .forEach(($item) => {
            $('.external-dashboards').append($item)
        });

    $('[data-target="#add-dashboard-modal"]')
        .attr(`disabled`, dashboards.length >= MAX_NUMBER_PAGES);
}

function createDashboardSelectorItem(dashboard) {
    const index = dashboard.index;
    const currentDashboardIndex = current && current.index;
    const subContainerClasses = (index === currentDashboardIndex ? 'bg-light rounded bold' : '');

    let name;

    if (dashboard.name.length >= 20) {
        name = $.trim(dashboard.name).substring(0, 17) + "...";
    } else {
        name = dashboard.name;
    }

    const $link = $('<a/>', {
        href: `#${index + 1}`,
        title: dashboard.name,
        text: name,
        class: 'mr-2'
    });

    const $dropdown = $(`
        <div class="dropdown d-inline-flex">
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
    `);

    return $('<div/>', {
        class: `d-flex align-items-center mx-1 p-2 ${subContainerClasses}`,
        html: [
            $link,
            $dropdown
        ]
    });
}

function createExternalDashboardLink(dashboard) {
    return $(`
        <a class="dropdown-item">
            <i class="fas fa-external-link-alt"></i> Dashboard "${dashboard.name}"
        </a>
    `);
}

/**
 * @param {boolean=false} init
 */
function loadCurrentDashboard(init = false) {
    if (init) {
        recalculateIndexes();
    }

    if (dashboards && dashboards.length > 0) {
        let hash = window.location.hash.replace(`#`, ``);
        if (!hash || !dashboards[hash - 1]) {
            hash = 1;
        }

        if (!dashboards[hash - 1]) {
            console.error(`Unknown dashboard "${hash}"`);
        } else {
            current = dashboards[hash - 1];
            window.location.hash = `#${hash}`;
        }
    }
    // no pages already saved
    else if (window.location.hash) {
        window.location.hash = '';
    }

    renderCurrentDashboard();
    updateAddRowButton();
    renderDashboardPagination();
}

function onDashboardSaved() {
    const content = {
        dashboards: JSON.stringify(dashboards)
    };

    return $.post(Routing.generate(`save_dashboard_settings`), content)
        .then(function (data) {
            if (data.success) {
                showBSAlert("Dashboards enregistrés avec succès", "success");
                dashboards = JSON.parse(data.dashboards);

                loadCurrentDashboard(false);
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
}

function onPageAdded() {
    const $dashboardNameInput = $('input[name="add-dashboard-name"]');
    const name = $dashboardNameInput.val();
    const $modal = $dashboardNameInput.closest('.modal');
    if(name) {
        $dashboardNameInput.val(``);

        if (dashboards.length >= MAX_NUMBER_PAGES) {
            console.error("Too many dashboards");
        } else {
            current = {
                updated: true,
                index: dashboards.length,
                name,
                rows: [],
            };
            dashboards.push(current);

            renderDashboardPagination();
            renderCurrentDashboard();
            updateAddRowButton();
            $modal.modal('hide');
        }
    }
    else {
        showBSAlert("Veuillez renseigner un nom de dashboard.", "danger");
    }
}

function onPageDeleted() {
    const dashboard = Number($(this).data(`dashboard`));

    dashboards.splice(dashboard, 1);
    recalculateIndexes();

    if (dashboards.length === 0) {
        current = undefined;
    } else if (dashboard === current.index) {
        current = dashboards[0];
    }
    somePagesDeleted = true;

    renderCurrentDashboard();
    renderDashboardPagination();
    updateAddRowButton();
}

function onRowAdded() {
    const $newRowColumnsCountInput = $('input[name="new-row-columns-count"]');

    const columns = $newRowColumnsCountInput.val();
    $newRowColumnsCountInput.val(``);
    $dashboardRowSelector.removeClass("selected");
    $addRowButton.attr(`disabled`, true);

    if (current) {
        current.updated = true;
        current.rows.push({
            index: current.rows.length,
            size: columns,
            updated: true,
            components: []
        });

        renderCurrentDashboard();
    }

    updateAddRowButton();
    $addRowButton.closest('.modal').modal('hide');
}

function onRowDeleted() {
    const $row = $(this).parent();
    const rowIndex = $row.data(`row`);

    $row.remove();
    if (current) {
        current.updated = true;
        current.rows.splice(rowIndex, 1);
    }

    recalculateIndexes();
    updateAddRowButton();
}

function onComponentEdited() {
    const $button = $(this);
    const {row, component} = getComponentFromTooltipButton($button);
    openModalComponentTypeSecondStep(
        $button,
        row.index,
        component
    );
}

function onComponentDeleted() {
    const $button = $(this);
    const $component = $button.closest('.dashboard-component');

    const {row, component} = getComponentFromTooltipButton($button);
    const componentIndex = component.index;

    current.updated = true;
    row.updated = true;
    delete row.components[componentIndex];

    $component.replaceWith(renderConfigComponent(componentIndex));
}

function getComponentFromTooltipButton($button) {
    const $component = $button.closest('.dashboard-component');
    const componentIndex = $component.data(`component`);
    const rowIndex = $component.closest(`.dashboard-row`).data(`row`);
    const row = current.rows[rowIndex];
    return {
        row,
        component: row.components[componentIndex]
    };
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
        const $form = $button.closest('.form');
        const rowIndex = $form.find('[name="rowIndex"]').val();
        const componentIndex = $form.find('[name="componentIndex"]').val();
        const componentTypeName = $button.data('component-type-name');

        openModalComponentTypeSecondStep($button, rowIndex, {
            index: componentIndex,
            config: {
                title: componentTypeName,
            },
            type: componentTypeId
        });
    }
}

function openModalComponentTypeSecondStep($button, rowIndex, component) {
    wrapLoadingOnActionButton($button, () => $.post(
        Routing.generate('dashboard_component_type_form', {componentType: component.type}),
        {
            rowIndex,
            componentIndex: component.index,
            values: JSON.stringify(component.config || {})
        },
        function (data) {
            if (data.html) {
                initSecondStep(data.html);
            }
            else {
                editComponent(rowIndex, component.index, {
                    config: component.config,
                    type: component.type,
                    meterKey: component.meterKey
                });
            }

            $modalComponentTypeFirstStep.modal('hide');

            if(data.html) {
                $modalComponentTypeSecondStep.modal('show');
            }
        },
        'json'
    ), true);
}

function onComponentSaved($modal) {
    clearFormErrors($modal);
    const {success, errorMessages, $isInvalidElements, data} = ProcessForm($modal);

    if (success) {
        const {rowIndex, componentIndex, meterKey, componentType, ...config} = data;
        editComponent(rowIndex, componentIndex, {
            config,
            type: componentType,
            meterKey
        });

        $modalComponentTypeSecondStep.modal('hide');
    } else {
        displayFormErrors($modal, {
            $isInvalidElements,
            errorMessages
        });
    }
}

function editComponent(rowIndex, componentIndex, {config, type, meterKey}) {
    const currentRow = getCurrentDashboardRow(rowIndex);

    if (currentRow && componentIndex < currentRow.size) {
        current.updated = true;
        currentRow.updated = true;

        let currentComponent = getRowComponent(currentRow, componentIndex);
        if (!currentComponent) {
            currentComponent = {index: componentIndex};
            currentRow.components[componentIndex] = currentComponent;
        }

        currentComponent.updated = true;
        currentComponent.config = config;
        currentComponent.type = type;
        currentComponent.meterKey = meterKey;

        const $currentComponent = $dashboard
            .find(`.dashboard-row[data-row="${rowIndex}"]`)
            .find(`.dashboard-component[data-component="${componentIndex}"]`);
        $currentComponent.replaceWith(renderConfigComponent(currentComponent));
    }
}

function initSecondStep(html) {
    const $modalComponentTypeSecondStepContent = $modalComponentTypeSecondStep.find('.content');
    $modalComponentTypeSecondStepContent.html('');
    $modalComponentTypeSecondStepContent.html(html);

    Select2.location($modalComponentTypeSecondStep.find('.ajax-autocomplete-location'));
    Select2.carrier($modalComponentTypeSecondStep.find('.ajax-autocomplete-carrier'));

    const $submitButton = $modalComponentTypeSecondStep.find('button[type="submit"]');
    $submitButton.off('click');
    $submitButton.on('click', () => onComponentSaved($modalComponentTypeSecondStep));

    renderFormComponentExample();
    const $formInputs = $modalComponentTypeSecondStep.find('select.data, input.data, input.checkbox');
    $formInputs.off('change.secondStepComponentType');
    $formInputs.on('change.secondStepComponentType', () => renderFormComponentExample());
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

/**
 * @returns {boolean}
 */
function hasEditDashboard() {
    // we return undefined to not trigger the browser alert
    return somePagesDeleted
        || dashboards.some(({updated: pageUpdated, rows}) => (
            pageUpdated
            || (
                rows
                && rows.some(({updated: rowUpdated, components}) => (
                    rowUpdated
                    || (
                        components
                        && components.some(({updated: componentUpdated}) => componentUpdated)
                    )
                ))
            )
        ))
        || undefined;
}

function renderFormComponentExample() {
    const $exampleContainer = $modalComponentTypeSecondStep.find('.component-example-container');
    const $exampleContainerParent = $exampleContainer.parent();

    const componentType = $exampleContainer.data('component-type');
    const {data: formData} = ProcessForm($modalComponentTypeSecondStep);

    return renderComponentExample($exampleContainer, componentType, $exampleContainer.data('meter-key'), formData)
        .then((renderingSuccess) => {
            if (renderingSuccess) {
                $exampleContainerParent.removeClass('d-none');
            } else {
                $exampleContainerParent.addClass('d-none');
            }
        })
        .catch(() => {
            $exampleContainerParent.addClass('d-none');
        });
}

function renderComponentExample($container, componentType, meterKey, formData, initData) {
    let exampleValuesPromise;
    console.log(componentType)
    if (initData) {
        exampleValuesPromise = new Promise((resolve) => {
            resolve({exampleValues: initData});
        });
    } else {
        exampleValuesPromise = $.post(
            Routing.generate('dashboard_component_type_example_values', {componentType}),
            {values: JSON.stringify(formData)}
        );
    }

    return exampleValuesPromise.then(({exampleValues}) => renderComponent(meterKey, $container, exampleValues, true));
}
