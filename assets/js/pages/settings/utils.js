import EditableDatatable, {SAVE_MANUALLY, STATE_EDIT, STATE_VIEWING} from "@app/editatable";
import Flash from "@app/flash";
import Routing from '@app/fos-routing';
import {generateRandomNumber} from "@app/utils";

global.toggleFrequencyInput = toggleFrequencyInput;

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

    const $tableFreeFields = $container.find(`.free-field-table`);
    const $tableManagement = $container.find(`.management-table`);
    const $editFreeFieldButton = $container.find(`.edit-free-fields-button`);
    const $addButton = $container.find(`.add-entity`);
    const $boxEditFreeFields = $container.find('.box-edit-free-fields');
    const $typeSelection = $container.find('.type-selection');
    const $editTypeButton = $container.find('.edit-type-button');
    const $typeIdHidden =  $container.find("[name='typeId']");
    $typeIdHidden.val($container.find(`[name=entity]`).val());

    $managementButtons.addClass('d-none');
    $editFreeFieldButton.removeClass('d-none');
    $tableFreeFields.attr(`id`, `table-${generateRandomNumber()}`);
    $tableManagement.attr(`id`, `table-${generateRandomNumber()}`);

    loadItems($container, config, selectedEntity);
    if($tableFreeFields.length > 0) {
        const tableFreeFields = EditableDatatable.create(`#${$tableFreeFields.attr(`id`)}`, {
            name: config.tableFreeFields.name,
            mode: config.mode,
            save: SAVE_MANUALLY,
            route: config.tableFreeFields.route(config.category),
            deleteRoute: config.tableFreeFields.deleteRoute,
            form: config.tableFreeFields.form,
            ordering: true,
            search: true,
            paging: true,
            columns: config.tableFreeFields.columns,
            minimumRows: config.tableFreeFields.minimumRows,
            onEditStart: () => {
                $editFreeFieldButton.addClass('d-none');
                $managementButtons.removeClass('d-none');
                $('.box-edit-type').addClass('d-none');
            },
            onEditStop: (apiResult) => {
                $managementButtons.addClass('d-none');
                $editFreeFieldButton.removeClass('d-none');
                $('.box-edit-type').removeClass('d-none');
            },
        });

        $editFreeFieldButton.on(`click`, function () {
            tableFreeFields.toggleEdit(STATE_EDIT, true);
        });
    }

    const tableManagement = EditableDatatable.create(`#${$tableManagement.attr(`id`)}`, {
        name: config.tableManagement.name,
        mode: config.mode,
        save: SAVE_MANUALLY,
        route: config.tableManagement.route($typeIdHidden.val()),
        deleteRoute: config.tableManagement.deleteRoute,
        form: config.tableManagement.form,
        ordering: true,
        search: true,
        paging: true,
        columns: config.tableManagement.columns,
        minimumRows: config.tableManagement.minimumRows,
        onEditStart: () => {
            //$container.find('.management-header').addClass('d-none'); //le header ne reviens pas
            $boxEditFreeFields.addClass('d-none');
            $managementButtons.removeClass('d-none');
            $editTypeButton.addClass('d-none');
            $typeSelection.addClass('d-none');
            const $itemContainer = $container.find(`.main-entity-content`);

            if (!$itemContainer.hasClass('main-entity-content-form')) {
                loadItems($container, config, selectedEntity, true).then(() => {
                    toggleCreationForm(undefined, $itemContainer, $itemContainer.hasClass('creation-mode'));
                });
            }

            if(config.onEditStart) {
                config.onEditStart();
            }
        },
        onEditStop: (apiResult) => {
            $boxEditFreeFields.removeClass('d-none');
            $managementButtons.addClass('d-none');
            $editTypeButton.removeClass('d-none');
            $typeSelection.removeClass('d-none');

            loadItems($container, config, selectedEntity, false)
            if(config.onEditStart) {
                config.onEditStop();
            }
        },
    });

    $container.find(`[name=entity]`).on(`change`, function () {
        selectedEntity = $(this).val();
        $typeIdHidden.val(selectedEntity);
        loadItems($container, config, selectedEntity, tableManagement.state !== STATE_VIEWING);
        if (tableManagement) {
            tableManagement.setURL(config.tableManagement.route(selectedEntity))
        }
    });

    $addButton.on(`click`, function() {
        selectedEntity = null;
        $typeIdHidden.val(null);
        $container.find(`.management-body`).removeClass('d-none');
        $container.find('.management-header').addClass('d-none');
        if (tableManagement) {
            tableManagement.setURL(config.tableManagement.route(selectedEntity))
        }
        tableManagement.toggleEdit(STATE_EDIT, true);
    });


    $editTypeButton.on(`click`, function () {
        tableManagement.toggleEdit(STATE_EDIT, true);
    });

    if (config.header && config.header.delete) {
        fireRemoveMainEntityButton($container, config.header.delete);
    }

    return tableManagement;
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
                        const $addButton = $container.find(`.add-entity`);
                        const $deleteButton = $container.find('.delete-main-entity');
                        const $managementBody = $container.find('.management-body');
                        const $managementHeader = $container.find('.management-header');

                        $editButton.on('click', function () {
                            $managementBody.css('margin-top', '0');
                            $managementBody.css('border-top-left-radius', '0').css('border-top-right-radius', '0');
                            $managementHeader.css('border-bottom-left-radius', '0').css('border-bottom-right-radius', '0');
                        });

                        if (!Boolean(edit)) {
                            $managementBody.css('margin-top', 15);
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
                            const isSmaller = $element && $element.hasClass('smaller');
                            const wiiTextBody = `<span class="wii-body-text">${value}</span>`;
                            const fixedClass = item.class;
                            const noFullWidth = item.noFullWidth;

                            const label = item.label !== undefined ? `<span class="wii-field-name">${item.label}</span>` : ' ';
                            $itemContainer.append(`
                                <div class="main-entity-content-item ${item.wide ? `col-md-6` : (isBigger ? "col-md-4" : isSmaller ? "col-md-2" : "col-md-3")} col-12 ${item.hidden ? `d-none` : ``} ${fixedClass ? fixedClass : ''}"
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
        $pageHeader?.addClass('d-none');
        $form.closest('.wii-section').find('.translate-labels').addClass('d-none');
        $category.addClass('data');
    }
    else {
        $pageHeader?.removeClass('d-none');
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
        const inputId = `entity-${generateRandomNumber()}`;
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

    const $editButton = $container.find(`.edit-type-button`);
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
    const $editButton = $container.find(`.edit-type-button`);
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

export function toggleFrequencyInput($input) {
    const $modal = $input.closest('.modal');
    const $globalFrequencyContainer = $modal.find('.frequency-content');
    const inputName = $input.attr('name');
    const $inputChecked = $modal.find(`[name="${inputName}"]:checked`);
    const inputCheckedVal = $inputChecked.val();

    $globalFrequencyContainer.addClass('d-none');
    $globalFrequencyContainer.find('.frequency').addClass('d-none');
    $globalFrequencyContainer
        .find('input.frequency-data, select.frequency-data')
        .removeClass('data')
        .removeClass('needed');
    $globalFrequencyContainer.find('.is-invalid').removeClass('is-invalid');

    if(inputCheckedVal) {
        $globalFrequencyContainer.removeClass('d-none');
        const $frequencyContainer = $globalFrequencyContainer.find(`.frequency.${inputCheckedVal}`);
        $frequencyContainer.removeClass('d-none');
        $frequencyContainer
            .find('input.frequency-data, select.frequency-data')
            .addClass('needed')
            .addClass('data');
    }

    $modal.find('.select-all-options').on('click', onSelectAll);
}

export function onSelectAll() {
    const $select = $(this).closest(`.input-group`).find(`select`);

    $select.find(`option:not([disabled])`).each(function () {
        $(this).prop(`selected`, true);
    });

    $select.trigger(`change`);
}
