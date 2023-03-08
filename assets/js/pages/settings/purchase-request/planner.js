import AJAX, {DELETE, GET, POST} from "@app/ajax";
import Form from "@app/form";
import {toggleFrequencyInput} from "@app/pages/settings/utils";

global.openFormPurchaseRequestPlanner = openFormPurchaseRequestPlanner;
export function initializePurchaseRequestPlanner($container) {
    const tablePurchaseRequestPlannerConfig = {
        ajax: {
            "url": Routing.generate('purchase_request_schedule_rule_api', true),
            "type": GET
        },
        order: [[1, "asc"]],
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `zone`, title: `Zone`},
            {data: `supplier`, title: `Fournisseur`, required: true},
            {data: `requester`, title: `Demandeur`},
            {data: `emailSubject`, title: `Objet du mail`},
            {data: `createdAt`, title: `Date de création`},
            {data: `frequency`, title: `Fréquence`},
            {data: `lastExecution`, title: `Dernière exécution`},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };

    const $tablePurchaseRequestPlanner = $container.find('#purchasesRulesTable');
    const tablePurchaseRequestPlanner = initDataTable($tablePurchaseRequestPlanner, tablePurchaseRequestPlannerConfig);

    Form
        .create($container.find('#modalFormPurchaseRequestPlanner'))
        .onSubmit((data, form) => {
            const $checkedFrequency = form.element.find('[name=frequency]:checked');
            if ($checkedFrequency.exists()) {
                toggleFrequencyInput($checkedFrequency);
            }
        })
        .addProcessor((data, errors, $form) => {
            const $inputs = $form.find( '.frequency-content input:visible, .frequency-content select:visible');
            $inputs.each((index, input) => {
                const $input = $(input);
                if (!$input.val()) {
                    $input.addClass('is-invalid');
                } else {
                    data.append($input.attr('name'), $input.val());
                }
            });
        })
        .submitTo(POST, 'purchase_request_schedule_form_submit', {
            table : tablePurchaseRequestPlannerConfig
        });

    $tablePurchaseRequestPlanner.on('click', '.delete-purchase-request-schedule-rule', function () {
        AJAX
            .route(DELETE, 'purchase_request_schedule_rule_delete', {id: $(this).data('id')})
            .json()
            .then((data) => {
                if (data.success) {
                    tablePurchaseRequestPlanner.ajax.reload();
                }
            })
    });
}

function openFormPurchaseRequestPlanner($button){
    const $modal = $('#modalFormPurchaseRequestPlanner');
    const $loaderWrapper = $button.closest('table').length ? $button.closest('table') : $button
    Modal.load('purchase_request_schedule_form', {id: $button.data('id')}, $modal, $loaderWrapper);
}
