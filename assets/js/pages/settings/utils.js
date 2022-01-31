import EditableDatatable, {MODE_MANUAL, MODE_NO_EDIT, SAVE_MANUALLY, STATE_VIEWING} from "../../editatable";

const $managementButtons = $(`.save-settings,.discard-settings`);

export function createManagementPage($container, config) {
    let selectedEntity = $container.find(`[name=entity]:first`).attr(`value`);

    const $table = $container.find(`.subentities-table`);
    const $editButton = $container.find(`.edit-button`);
    const $addButton = $container.find(`.add-entity`);

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
        ordering: true,
        columns: config.table.columns,
        onEditStart: () => {
            $editButton.addClass('d-none');
            $managementButtons.removeClass('d-none');

            loadItems($container, config, selectedEntity, true);
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
            $editButton.removeClass('d-none');
            $addButton.removeClass(`d-none`);

            if(selectedEntity === null) {
                window.location.reload();
            } else {
                loadItems($container, config, selectedEntity, false);
            }
        },
    });

    $container.find(`[name=entity]`).on(`change`, function() {
        selectedEntity = $(this).val();

        loadItems($container, config, selectedEntity, table.state !== STATE_VIEWING);
        table.setURL(config.table.route(selectedEntity))
    });

    $addButton.on(`click`, function() {
        const id = `entity-${Math.floor(Math.random() * 1000000)}`;
        selectedEntity = null;

        $addButton.addClass(`d-none`);
        $container.find(`[name=entity] + label:last`).after(`
            <input type="radio" id="${id}" name="entity" class="data">
            <label for="${id}">
                <span class="d-inline-flex align-items-center field-label nowrap">Nouveau type</span>
            </label>
        `);

        $container.find(`#${id}`).prop(`checked`, true);

        table.setURL(config.table.route(selectedEntity))
        table.toggleEdit(false, true);
    });

    $editButton.on(`click`, function() {
        table.toggleEdit(undefined, true);
    });

    $container.on(`keyup`, `.main-entity-content-item [name=label]`, function() {
        $container.find(`[name=entity]:checked + label`).text($(this).val() || `Nouveau type`);
    })
}

function loadItems($container, config, type, edit) {
    const route = config.header.route(type, edit);
    const params = {
        types: $(`[name=entity]`).map((_, a) => $(a).attr(`value`)).toArray(),
    };

    $.post(route, params, function(data) {
        if(data.success) {
            const $itemContainer = $container.find(`.main-entity-content`);
            $itemContainer.empty();

            if(type === null) {
                $container.find(`input[name="entity"]:checked`).attr(`value`, data.category);
            }

            for(const item of data.data) {
                const value = item.value === undefined || item.value === null ? '' : item.value;
                $itemContainer.append(`
                    <div class="main-entity-content-item col-md-3 col-12 ${item.hidden ? `d-none` : ``}">
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
    });
}
