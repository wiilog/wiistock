import EditableDatatable, {MODE_MANUAL, MODE_NO_EDIT, SAVE_MANUALLY, STATE_EDIT, STATE_VIEWING} from "../../editatable";
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
    }
    else {
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
        mode: typeof config.edit === 'boolean' ? (config.edit ? MODE_MANUAL : MODE_NO_EDIT) : config.edit,
        save: SAVE_MANUALLY,
        route: config.table.route(selectedEntity),
        deleteRoute: config.table.deleteRoute,
        form: config.table.form,
        ordering: true,
        search: true,
        paging: true,
        columns: config.table.columns,
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
        },
        onEditStop: (apiResult) => {
            console.log(apiResult)
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
                    table.setURL(config.table.route(selectedEntity), false);
                }
            }

            loadItems($container, config, selectedEntity);
        },
    });

    $container.find(`[name=entity]`).on(`change`, function() {
        selectedEntity = $(this).val();

        loadItems($container, config, selectedEntity, table.state !== STATE_VIEWING);
        table.setURL(config.table.route(selectedEntity))
    });

    $addButton.on(`click`, function() {
        selectedEntity = null;

        $pageBody.removeClass('d-none');

        if (config.newTitle) {
            let $title = $pageBody.find('.wii-title')
            if (!$title.exists()) {
                $title = $(`<div class="header wii-title"></div>`);
                $pageBody.prepend($title);
            }
            $title.html(config.newTitle);
        }
        $pageBody.find(`.main-entity-content`).addClass('creation-mode');

        table.setURL(config.table.route(selectedEntity), false);
        table.toggleEdit(STATE_EDIT, true);
    });

    $editButton.on(`click`, function() {
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

                    for (const item of data.data) {
                        if (item.breakline) {
                            $itemContainer.append(`<div class="w-100"></div>`);
                        } else if (item.type === 'hidden') {
                            $itemContainer.append(`<input type="hidden" class="${item.class}" name="${item.name}" value="${item.value}"/>`);
                        } else {
                            const value = item.value === undefined || item.value === null ? '' : item.value;
                            const data = Object.entries(item.data || {})
                                .map(([key, value]) => `data-${key}="${value}"`)
                                .join(` `);

                            $itemContainer.append(`
                                <div class="main-entity-content-item col-md-3 col-12 ${item.hidden ? `d-none` : ``}"
                                     ${data}>
                                    <div class="d-flex align-items-center py-2">
                                        ${item.icon ? `<img src="/svg/reference_article/${item.icon}.svg" alt="Icône" width="20px">` : ``}
                                        <div class="d-grid w-100">
                                            <span class="wii-field-name">${item.label}</span>
                                            <span class="wii-body-text">${value}</span>
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

function fireRemoveMainEntityButton($container, deleteConfig) {
    const $deleteMainEntityButton = $container.find(`.delete-main-entity`);
    const $modal = $container.find('.modalDeleteEntity');
    const $spinnerContainer = $modal.find('.spinner-container');
    const $messageContainer = $modal.find('.message-container');
    const $submitButton = $modal.find('.submit-danger');
console.log($modal);
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

                                        removeEntity($container, selectedEntity);
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
    console.log($entity)
    if ($entity.is('select')) {
        $entity
            .append(new Option(entity.label, entity.id, true, true))
            .trigger('change');
    }
    else {
        const inputId = `entity-${Math.floor(Math.random() * 1000000)}`;
        $container.find(`[name=entity] + label:last`).after(`
            <input type="radio" id="${inputId}" name="entity" value="${entity.id}" class="data" checked>
            <label for="${inputId}">
                <span class="d-inline-flex align-items-center field-label nowrap">${entity.label}</span>
            </label>
        `);

        $container.find(`#${entity.id}`).prop(`checked`, true);
    }
}

function removeEntity($container, entityToRemove) {
    const $entity = $container.find(`[name=entity]`);
    const $managementBody = $container.find(`.management-body`);
    let nextValue;

    if ($entity.is('select')) {
        const $removed = $entity.find(`[value=${entityToRemove}]`);
        $removed.remove();
        const $nextSelectedOption = $entity
            .find('option')
            .first();
        nextValue = $nextSelectedOption.exists()
            ? $nextSelectedOption.attr('value')
            : undefined;
        $entity
            .val(nextValue)
            .trigger('change');
    }
    else { // is wii-expanded-switch
        const $removed = $entity.find(`[value=${entityToRemove}]`);
        const $labelToRemove = $container.find(`[for=${$removed.attr('id')}]`);
        $removed.remove();
        $labelToRemove.remove();

        const $nextSelectedInput = $entity
            .filter('input')
            .first();
        nextValue = $nextSelectedInput.val();
        if ($nextSelectedInput.exists()) {
            $nextSelectedInput.prop('checked', true);
        }
    }

    $managementBody.toggleClass('d-none', !nextValue);
}
