import EditableDatatable, {MODE_MANUAL, MODE_NO_EDIT, SAVE_MANUALLY, STATE_EDIT, STATE_VIEWING} from "../../editatable";

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
        search: true,
        paging: true,
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
        selectedEntity = null;

        $addButton.addClass(`d-none`);
        if($container.find(`select[name=entity]`).exists()) {
            $container.find(`select[name=entity]`).append(new Option(config.header.new, ``, true, true));
        } else {
            const id = `entity-${Math.floor(Math.random() * 1000000)}`;

            $container.find(`[name=entity] + label:last`).after(`
                <input type="radio" id="${id}" name="entity" class="data">
                <label for="${id}">
                    <span class="d-inline-flex align-items-center field-label nowrap">${config.header.new}</span>
                </label>
            `);

            $container.find(`#${id}`).prop(`checked`, true);
        }

        table.setURL(config.table.route(selectedEntity), false);
        table.toggleEdit(STATE_EDIT, true);
    });

    $editButton.on(`click`, function() {
        table.toggleEdit(undefined, true);
    });

    $container.on(`keyup`, `.main-entity-content-item [name=label], .main-entity-content-item [name=name]`, function() {
        if($container.find(`select[name=entity]`).exists()) {
            const $select = $container.find(`select[name=entity]`);
            $select.find(`option:selected`).text($(this).val() || config.header.new);
        } else {
            $container.find(`[name=entity]:checked + label`).text($(this).val() || config.header.new);
        }
    });

    return table;
}

function loadItems($container, config, type, edit) {
    const route = config.header.route(type, edit);
    const params = {
        types: $container.find(`[name=entity]`).map((_, a) => $(a).attr(`value`)).toArray(),
    };

    $.post(route, params, function(data) {
        if(data.success) {
            const $itemContainer = $container.find(`.main-entity-content`);
            $itemContainer.empty();

            if(type === null) {
                $container.find(`input[name="entity"]:checked, select[name="entity"] option:selected`).attr(`value`, data.category || config.category);
            }

            for(const item of data.data) {
                if(item.breakline) {
                    $itemContainer.append(`<div class="w-100"></div>`);
                }
                else {
                    const value = item.value === undefined || item.value === null ? '' : item.value;
                    const data = Object.entries(item.data || {})
                        .map(([key, value]) => `data-${key}="${value}"`)
                        .join(` `);

                    $itemContainer.append(`
                        <div class="main-entity-content-item col-md-3 col-12 ${item.hidden ? `d-none` : ``}" ${data}>
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
    });
}
