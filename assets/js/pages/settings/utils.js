import EditableDatatable, {SAVE_MANUALLY, STATE_EDIT, STATE_VIEWING} from "../../editatable";
import Flash from "../../flash";

const $managementButtons = $(`.save-settings,.discard-settings`);

export function createManagementPage($container, config) {
    const $selectedEntity = $container.find(`[name=entity]:first`);

    let selectedEntity;
    if ($selectedEntity.is('select')) {
        const $firstOption = $selectedEntity.find('option').eq(0);
        selectedEntity = $firstOption.attr(`value`);
        $selectedEntity
            .val(selectedEntity)
            .trigger('change');
    } else {
        $selectedEntity.prop('checked', true);
        selectedEntity = $selectedEntity.attr(`value`);
    }

    const $table = $container.find(`.subentities-table`);
    const $editButton = $container.find(`.edit-button`);
    const $addButton = $container.find(`.add-entity`);
    const $pageHeader = $container.find(`.management-header`);
    const $pageBody = $container.find(`.management-body`);

    $managementButtons.addClass('d-none');
    $editButton.removeClass('d-none');
    $table.attr(`id`, `table-${Math.floor(Math.random() * 1000000)}`);

    loadItems($container, config, selectedEntity);
    const table = EditableDatatable.create(`#${$table.attr(`id`)}`, {
        name: config.name,
        mode: config.mode,
        save: SAVE_MANUALLY,
        route: config.table.route(selectedEntity),
        deleteRoute: config.table.deleteRoute,
        form: config.table.form,
        ordering: true,
        search: true,
        paging: true,
        columns: config.table.columns,
        minimumRows: config.table.minimumRows,
        onEditStart: () => {
            $editButton.addClass('d-none');
            $addButton.addClass('d-none');
            $managementButtons.removeClass('d-none');

            const $itemContainer = $container.find(`.main-entity-content`);

            if (!$itemContainer.hasClass('main-entity-content-form')) {
                loadItems($container, config, selectedEntity, true).then(() => {
                    toggleCreationForm($pageHeader, $itemContainer, $itemContainer.hasClass('creation-mode'));
                });
            }

            if(config.onEditStart) {
                config.onEditStart();
            }
        },
        onEditStop: (apiResult) => {
            $managementButtons.addClass('d-none');
            $editButton.removeClass('d-none');
            $addButton.removeClass(`d-none`);

            toggleCreationForm($pageHeader, $container.find(`.main-entity-content`), false);
            $pageBody.find('.header').remove();

            if (apiResult) { // if type was created in edit mode
                const entity = apiResult.entity;
                if (selectedEntity !== entity.id) {
                    selectedEntity = entity.id;
                    addNewEntity($container, entity);
                    table.setURL(config.table.route(selectedEntity), true);
                }
            }

            loadItems($container, config, selectedEntity);

            $container.find(`.delete-main-entity`).removeClass(`d-none`);

            if(config.onEditStop) {
                config.onEditStop();
            }
        },
    });

    if (config.table.hidden) {
        const id = $table.attr(`id`);
        $('#' + id).addClass('d-none');
    }

    $container.find(`[name=entity]`).on(`change`, function () {
        selectedEntity = $(this).val();
        loadItems($container, config, selectedEntity, table.state !== STATE_VIEWING);
        if (table) {
            table.setURL(config.table.route(selectedEntity))
        }
    });

    $addButton.on(`click`, function() {
        selectedEntity = null;

        $container.find(`.delete-main-entity`).parent().addClass(`d-none`);
        $pageBody.removeClass('d-none');

        if (config.newTitle) {
            let $title = $pageBody.find('.wii-title')
            if (!$title.exists()) {
                $title = $(`<div class="header wii-title"></div>`);
                $pageBody.prepend($title);
            }
            $title.html(config.newTitle);
            $(`#page-title .bold`).text(config.newTitle);
        }

        $pageBody.find(`.main-entity-content`).addClass('creation-mode');
        table.setURL(config.table.route(selectedEntity), false);
        table.toggleEdit(STATE_EDIT, true);
    });

    $editButton.on(`click`, function () {
        table.toggleEdit(STATE_EDIT, true);
    });

    if (config.header && config.header.delete) {
        fireRemoveMainEntityButton($container, config.header.delete);
    }

    return table;
}

function loadItems($container, config, type, edit = false) {
    if (config.header && config.header.route) {
        const route = config.header.route(type, Number(edit));
        const params = {
            types: $container.find(`[name=entity]`)
                .map((_, a) => $(a).attr(`value`))
                .toArray(),
        };

        return new Promise((resolve) => {
            $.post(route, params, function (data) {
                if (data.success) {
                    const $itemContainer = $container.find(`.main-entity-content`);
                    $itemContainer.toggleClass('main-entity-content-form', Boolean(edit))
                    $itemContainer.empty();

                    if(config.name === "alertTemplates"){
                        const $editButton = $container.find(`.edit-button`);
                        const $pageHeader = $container.find(`.management-header div:last-child`);
                        const $addButton = $container.find(`.add-entity`);
                        const $deleteButton = $container.find('.delete-main-entity');
                        const $managementBody = $container.find('.management-body');
                        const $managementHeader = $container.find('.management-header');

                        $editButton.on('click', function () {
                            $pageHeader.addClass('d-none');
                            $managementBody.css('margin-top', '0');
                            $managementBody.css('border-top-left-radius', '0').css('border-top-right-radius', '0');
                            $managementHeader.css('border-bottom-left-radius', '0').css('border-bottom-right-radius', '0');
                        });

                        if (!Boolean(edit)) {
                            $managementBody.css('margin-top', 15);
                            $pageHeader.removeClass('d-none');
                        }

                        $addButton.on('click', function(){
                            $deleteButton.parent().addClass('d-none');
                            $editButton.parent().addClass('d-none');
                        })
                    }

                    for (const item of data.data) {
                        if (item.breakline) {
                            $itemContainer.append(`<div class="w-100"></div>`);
                        } else if (item.type === 'hidden') {
                            $itemContainer.append(`<input type="hidden" class="${item.class || ''}" name="${item.name}" value="${item.value}"/>`);
                        } else {
                            const value = item.value === undefined || item.value === null ? '' : item.value;
                            const data = Object.entries(item.data || {})
                                .map(([key, value]) => `data-${key}="${value}"`)
                                .join(` `);
                            const $element = $.isValidSelector(value) ? $(value) : null;
                            const isBigger = $element && $element.hasClass('bigger');
                            const wiiTextBody = `<span class="wii-body-text">${value}</span>`;
                            const fixedClass = item.class;
                            const noFullWidth = item.noFullWidth;

                            const label = item.label !== undefined ? `<span class="wii-field-name">${item.label}</span>` : ' ';
                            $itemContainer.append(`
                                <div class="main-entity-content-item ${item.wide ? `col-md-6` : (isBigger ? "col-md-4" : "col-md-3")} col-12 ${item.hidden ? `d-none` : ``} ${fixedClass ? fixedClass : ''}"
                                     ${data}>
                                    <div class="d-flex align-items-center py-2 w-100">
                                        ${item.icon ? `<img src="/svg/reference_article/${item.icon}.svg" alt="Icône" width="20px">` : ``}
                                        <div class="d-grid ${!isBigger && !noFullWidth ? "w-100" : ""}">
                                            ${label}
                                            ${isBigger ? value : wiiTextBody}
                                        </div>
                                    </div>
                                </div>
                            `);
                        }
                    }
                }

                resolve();
            });
        });
    }
    else {
        return new Promise((resolve) => {
            resolve();
        });
    }
}

export function fireRemoveMainEntityButton($container, deleteConfig) {
    const $deleteMainEntityButton = $container.find(`.delete-main-entity`);
    const $modal = $container.find('.modalDeleteEntity');
    const $spinnerContainer = $modal.find('.spinner-container');
    const $messageContainer = $modal.find('.message-container');
    const $submitButton = $modal.find('.submit-danger');

    $deleteMainEntityButton
        .off('click')
        .on(`click`, function () {
            const $selectedEntity = $container.find(`[name=entity]`);
            let selectedEntity;
            if ($selectedEntity.is('select')) {
                selectedEntity = $selectedEntity.val();
            }
            else { // is wii-expanded-switch
                const $selectedInput = $selectedEntity.filter(`:checked`);
                selectedEntity = $selectedInput.attr(`value`);
            }

            $spinnerContainer
                .addClass('d-flex')
                .removeClass('d-none');
            $messageContainer.addClass('d-none');
            $submitButton.addClass('d-none');
            $modal
                .find('.modal-title')
                .html(deleteConfig.modalTitle);
            $modal.modal('show');
            $.ajax({
                url: Routing.generate(deleteConfig.checkRoute, {[deleteConfig.selectedEntityLabel]: selectedEntity}, true),
                type: "get",
            })
                .then(({success, message}) => {
                    $spinnerContainer
                        .removeClass('d-flex')
                        .addClass('d-none');
                    $messageContainer
                        .html(message)
                        .removeClass('d-none');
                    $submitButton
                        .toggleClass('d-none', !success)
                        .off('click');
                    if (success) {
                        $submitButton
                            .off('click')
                            .on('click', () => {
                                $submitButton.pushLoader('white');
                                $.ajax({
                                    url: Routing.generate(deleteConfig.route, {[deleteConfig.selectedEntityLabel]: selectedEntity}, true),
                                    type: "POST",
                                })
                                    .then(({message}) => {
                                        Flash.add('success', message);
                                        $submitButton.popLoader();
                                        $modal.modal('hide');
                                        window.location.reload();
                                    })
                                    .catch(() => {
                                        $submitButton.popLoader();
                                        $modal.modal('hide');
                                    });
                            });
                    }
                    $spinnerContainer.addClass('d-none');
                });
        });
}

function toggleCreationForm($pageHeader, $form, show) {
    const $category = $form.find(`.category`);
    if (show) {
        $pageHeader.addClass('d-none');
        $category.addClass('data');
    }
    else {
        $pageHeader.removeClass('d-none');
        $category.removeClass('data');
    }

    $form
        .removeClass('creation-mode')
}

function addNewEntity($container, entity) {
    const $entity = $container.find(`[name=entity]`);
    if ($entity.is('select')) {
        $entity
            .append(new Option(entity.label, entity.id, true, true))
            .trigger('change');
    }
    else {
        const inputId = `entity-${Math.floor(Math.random() * 1000000)}`;
        const $newEntity = $(`
            <input type="radio" id="${inputId}" name="entity" value="${entity.id}" class="data" checked>
            <label for="${inputId}">
                <span class="d-inline-flex align-items-center field-label nowrap">${entity.label}</span>
            </label>
        `);
        const $switchContainer = $container.find(`.wii-expanded-switch[data-name=entity]`);
        const $lastEntity = $switchContainer.find(`[name=entity] + label:last`);

        if ($lastEntity.exists()) {
            $lastEntity.after($newEntity);
        }
        else {
            $switchContainer.html($newEntity);
        }

        $container.find(`#${entity.id}`).prop(`checked`, true);
    }
}

export function createManagementHeaderPage($container, config) {
    const $selectedEntity = $container.find(`[name=entity]:first`);

    const $firstOption = $selectedEntity.find('option').eq(0);
    let selectedEntity = $firstOption.attr(`value`);
    $selectedEntity
        .val(selectedEntity)
        .trigger('change');

    const $editButton = $container.find(`.edit-button`);
    const $addButton = $container.find(`.add-entity`);
    const $pageHeader = $container.find(`.management-header`);
    const $pageBody = $container.find(`.management-body`);

    $managementButtons.addClass('d-none');
    $editButton.removeClass('d-none');

    loadItems($container, config, selectedEntity);

    $container.find(`[name=entity]`).on(`change`, function () {
        selectedEntity = $(this).val();
        loadItems($container, config, selectedEntity);
    });

    $addButton.on(`click`, function() {
        selectedEntity = null;

        $pageBody.removeClass('d-none');
        $editButton.addClass(`d-none`);
        $managementButtons.removeClass(`d-none`);

        if (config.newTitle) {
            let $title = $pageBody.find('.wii-title')
            if (!$title.exists()) {
                $title = $(`<div class="header wii-title"></div>`);
                $pageBody.prepend($title);
            }
            $title.html(config.newTitle);
            $(`#page-title .bold`).text(config.newTitle);
        }
        $pageBody.find(`.main-entity-content`).addClass('creation-mode');
        const $itemContainer = $container.find(`.main-entity-content`);

        if (!$itemContainer.hasClass('main-entity-content-form')) {
            loadItems($container, config, selectedEntity, true).then(() => {
                toggleCreationForm($pageHeader, $itemContainer, $itemContainer.hasClass('creation-mode'));
            });
        }
    });

    $editButton.on(`click`, function () {
        $managementButtons.removeClass('d-none');
        $editButton.addClass('d-none');
        loadItems($container, config, selectedEntity, true);
    });

    if (config.header && config.header.delete) {
        fireRemoveMainEntityButton($container, config.header.delete);
    }
}

export function onHeaderPageEditStop($container, apiResult) {
    const $editButton = $container.find(`.edit-button`);
    const $pageHeader = $container.find(`.management-header`);
    const $pageBody = $container.find(`.management-body`);

    const $entity = $container.find(`[name=entity]`);

    $pageHeader.removeClass('d-none');
    $pageBody.removeClass('d-none');
    $editButton.removeClass(`d-none`);
    $managementButtons.addClass(`d-none`);

    $pageBody.find(`.wii-title`).remove();
    $pageBody.find(`.main-entity-content`).removeClass('creation-mode');

    if (apiResult && apiResult.entity) { // if alert template was created in edit mode
        const entity = apiResult.entity;
        addNewEntity($container, entity);
    }
    else {
        $entity.trigger(`change`);
    }
}
