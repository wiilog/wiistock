import {GET} from "@app/ajax";

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
    const tablePurchaseRequestPlanner = initDataTable(`purchasesRulesTable`, tablePurchaseRequestPlannerConfig);
}
