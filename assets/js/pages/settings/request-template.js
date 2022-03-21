import {createManagementPage} from "./utils";

export function initializeRequestTemplates($container, canEdit) {
    const delivery = $container.find('#delivery-template-type').length > 0;
    const collect = $container.find('#collect-template-type').length > 0;
    const handling = $container.find('#handling-template-type').length > 0;
    const type = $(delivery ? `#delivery-template-type` : (collect ? `#collect-template-type` : '#handling-template-type')).val();
    const quantityLabel = delivery ? `Quantité à livrer` : 'Quantité à collecter';
    const table = createManagementPage($container, {
        name: `requestTemplates`,
        edit: canEdit,
        newTitle: 'Ajouter un modèle de demande',
        category: type,
        header: {
            route: (template, edit) => Routing.generate('settings_request_template_header', {category: type, template, edit}, true),
            delete: {
                checkRoute: 'settings_request_template_check_delete',
                selectedEntityLabel: 'requestTemplate',
                route: 'settings_request_template_delete',
                modalTitle: 'Supprimer le modèle de demande',
            },
        },
        table: {
            route: (template) => Routing.generate('settings_request_template_api', {type, template}, true),
            deleteRoute: `settings_request_template_line_delete`,
            hidden: handling,
            columns: [
                {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder icon-column', orderable: false},
                {data: `reference`, title: `Référence`, required: true},
                {data: `label`, title: `Libellé`},
                {data: `location`, title: `Emplacement`},
                {data: `quantityToTake`, title: quantityLabel, required: true},
            ],
            form: {
                actions: `<button class="btn btn-silent delete-row"><i class="wii-icon wii-icon-trash text-primary"></i></button>`,
                reference: `<select name="reference" data-s2="reference" data-parent="body" required class="form-control data" data-global-error="Référence"></select>`,
                label: `<div class="template-label"></div>`,
                location: `<div class="template-location"></div>`,
                quantityToTake: `<input type="number" name="quantityToTake" required class="form-control data" data-global-error="${quantityLabel}"/>`,
            },
            minimumRows: 1,
        },
    });

    function onTypeChange() {
        $container.find(`.main-entity-content-item[data-type]`).addClass(`d-none`);

        $container.find(`.main-entity-content-item[data-type="${$(this).val()}"]`).each(function() {
            $(this).removeClass(`d-none`);
        })
    }

    $container.arrive(`[name="deliveryType"],[name="collectType"],[name="handlingType"]`, onTypeChange);
    $container.on(`change`, `[name="deliveryType"],[name="collectType"],[name="handlingType"]`, onTypeChange);

    $container.on(`change`, `[name="reference"]`, function() {
        const $select = $(this);
        const $row = $select.closest(`tr`);
        const data = $select.select2(`data`)[0];

        $row.find(`.template-label`).text(data.label)
        $row.find(`.template-location`).text(data.location)
        table.table.draw();
    });
}
