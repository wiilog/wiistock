import EditableDatatable, {MODE_MANUAL, MODE_NO_EDIT, SAVE_MANUALLY, STATE_VIEWING} from "../../editatable";
import Flash from "../../flash";

const $managementButtons = $(`.save-settings,.discard-settings`);

export function createManagementPage($container, config) {
    const $selectedEntity = $container.find(`[name=entity]:first`);
    $selectedEntity.prop('checked', true);
    let selectedEntity = $selectedEntity.attr(`value`);

    const $table = $container.find(`.subentities-table`);
    const $editButton = $container.find(`.edit-button`);

    $managementButtons.addClass('d-none');
    $editButton.removeClass('d-none');
    $table.attr(`id`, `table-${Math.floor(Math.random() * 1000000)}`);

    loadItems($container, config, selectedEntity, false);

    const table = EditableDatatable.create(`#${$table.attr(`id`)}`, {
        name: config.name,
        edit: config.edit ? MODE_MANUAL : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        route: config.table.route(selectedEntity),
        deleteRoute: config.table.deleteRoute,
        form: config.table.form,
        columns: config.table.columns,
        onEditStart: () => {
            $editButton.addClass('d-none');
            $managementButtons.removeClass('d-none');

            loadItems($container, config, selectedEntity, true);
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
            $editButton.removeClass('d-none');
            loadItems($container, config, selectedEntity, false)
        },
    });

    $container.find(`[name=entity]`).on(`change`, function() {
        selectedEntity = $(this).val();

        loadItems($container, config, selectedEntity, table.state !== STATE_VIEWING);
        table.setURL(config.table.route(selectedEntity))
    });

    $editButton.on(`click`, function() {
        table.toggleEdit(undefined, true);
    });

    if (config.header.delete) {
        fireRemoveMainEntityButton($container, config.header.delete);
    }
}

function loadItems($container, config, type, edit) {
    const route = config.header.route(type, edit);

    $.post(route, function(data) {
        if(data.success) {
            const $itemContainer = $container.find(`.main-entity-content`);
            $itemContainer.empty();

            for(const item of data.data) {
                const value = item.value === undefined || item.value === null ? '' : item.value;
                $itemContainer.append(`
                    <div class="col-auto ml-3 ${item.hidden ? `d-none` : ``}">
                        <div class="d-flex justify-content-center align-items-center py-2">
                            ${item.icon ? `<img src="/svg/reference_article/${item.icon}.svg" alt="Icône" width="20px">` : ``}
                            <div class="d-grid">
                                <span class="wii-field-name">${item.label}</span>
                                <span class="wii-body-text">${value}</span>
                            </div>
                        </div>
                    </div>
                `);
            }
        }
    });
}

function fireRemoveMainEntityButton($container, deleteConfig) {
    const $deleteMainEntityButton = $container.find(`.delete-main-entity`);
    const $modal = $(deleteConfig.modal);
    const $spinnerContainer = $modal.find('.spinner-container');
    const $messageContainer = $modal.find('.message-container');
    const $submitButton = $modal.find('.submit-danger');

    $deleteMainEntityButton.on(`click`, function () {
        const $selectedEntity = $container.find(`[name=entity]:checked`);
        const selectedEntity = $selectedEntity.attr(`value`);

        $spinnerContainer
            .addClass('d-flex')
            .removeClass('d-none');
        $messageContainer.addClass('d-none');
        $submitButton.addClass('d-none');
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
                    $submitButton.on('click', () => {
                        $submitButton.pushLoader('white');
                        $.ajax({
                            url: Routing.generate(deleteConfig.route, {[deleteConfig.selectedEntityLabel]: selectedEntity}, true),
                            type: "POST",
                        })
                            .then(({message}) => {
                                Flash.add('success', message);
                                $submitButton.popLoader();
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
