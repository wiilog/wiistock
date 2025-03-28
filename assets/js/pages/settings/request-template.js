import {createManagementPage} from "./utils";
import Routing from '@app/fos-routing';
import DeliveryRequestTemplateUsageEnum from "@generated/delivery-request-template-usage-enum.js";

const USAGES_LINES_NEEDED = [DeliveryRequestTemplateUsageEnum.TRIGGER_ACTION.value];
const USAGES_LOGO_NEEDED = [DeliveryRequestTemplateUsageEnum.SLEEPING_STOCK.value];

export function initializeRequestTemplates($container, canEdit) {
    const delivery = $container.find('#delivery-template-type').length > 0;
    const collect = $container.find('#collect-template-type').length > 0;
    const handling = $container.find('#handling-template-type').length > 0;
    const type = $(delivery ? `#delivery-template-type` : (collect ? `#collect-template-type` : '#handling-template-type')).val();
    const quantityLabel = delivery ? `Quantité à livrer` : 'Quantité à collecter';
    const $entitySelect =  $container.find(`[name="entity"]`);
    const table = createManagementPage($container, {
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
        tableManagement: {
            name: `requestTemplates`,
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
            minimumRows: handling ? 0 : 1,
        },
        onEditStop: () => {
            const $typeIdHidden =  $container.find("[name='typeId']");
            if (!$typeIdHidden.val()) {
                window.location.reload();
            }
            $entitySelect.removeClass(`d-none`);
        },
        onEditStart: () => {
            const $typeIdHidden =  $container.find("[name='typeId']");
            const $deleteButton = $container.find('.delete-main-entity');
            $deleteButton.toggleClass('d-none', !$typeIdHidden.val())
            $entitySelect.addClass(`d-none`);
        }
    });

    $(document)
        .off('change.templateType')
        .on('change.templateType', `[name="deliveryType"],[name="collectType"],[name="handlingType"]`, () => onTypeChange($container));

    $(document)
        .off('change.entitySelect')
        .on('change.entitySelect', function (event) {
            const deliveryRequestUsage = $(event.target).find('option:selected').data('delivery-request-usage');
            onDeliveryRequestTemplateUsageChange($container, deliveryRequestUsage, table);
        });

    $(document)
        .off('change.deliveryRequestTemplateUsage')
        .on('change.deliveryRequestTemplateUsage', '[name="deliveryRequestTemplateUsage"]', () => onDeliveryRequestTemplateUsageChange($container, $container.find(`[name="deliveryRequestTemplateUsage"]`).val(), table));

    $(document).arrive('[name="deliveryRequestTemplateUsage"]', function() {
        $(this).trigger('change');
    });

    $container.on(`change`, `[name="reference"]`, function () {
        const $select = $(this);
        const $row = $select.closest(`tr`);
        const data = $select.select2(`data`)[0];

        $row.find(`.template-label`).text(data.label);
        $row.find(`.template-location`).text(data.location);
        table.table.draw();
    });
}

function onDeliveryRequestTemplateUsageChange($container, usage,  table) {
    if(usage) {
        const isLinesNeeded = USAGES_LINES_NEEDED.includes(usage);
        $container.find('.template-references-table-container').toggleClass('d-none', !isLinesNeeded);
        table.config.minimumRows = isLinesNeeded ? 1 : 0;

        const isLogoNeeded = USAGES_LOGO_NEEDED.includes(usage);
        $container.find('.main-entity-content-item [name="logo"]').closest('.main-entity-content-item').toggleClass('d-none', !isLogoNeeded);
    }
}

function onTypeChange($container) {
    $container.find(`.main-entity-content-item[data-type]`).addClass(`d-none`);

    $container.find(`.main-entity-content-item[data-type="${$(this).val()}"]`).each(function () {
        $(this).removeClass(`d-none`);
    });
}
