import AJAX, {GET} from "@app/ajax";
import Form from "@app/form";

let modalNewInventoryPanner = '#modalNewInventoryPlanner';
let $modalNewInventoryPanner = $(modalNewInventoryPlanner);
let tableInventoryPanning;

export function initializeInventoryPlanificatorTable($container) {

    const tableInventoryPannerConfig = {
        ajax: {
            "url": Routing.generate('settings_mission_rules_api', true),
            "type": GET
        },
        order: [[1, "asc"]],
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `missionType`, title: `Type de mission`, required: true},
            {data: `label`, title: `Libellé`, required: true},
            {data: `periodicity`, title: `Périodicité`, required: true},
            {data: `categories`, title: `Catégorie(s)`, required: true},
            {data: `duration`, title: `Durée`, required: true},
            {data: `creator`, title: `Créateur`, required: true},
            {data: `lastExecution`, title: `Dernière exécution`, required: true},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    tableInventoryPanning = initDataTable(`missionRulesTable`, tableInventoryPannerConfig);

    const formNewInventoryPanner = Form
        .create(modalNewInventoryPanner, {clearOnOpen: true})
        .submitTo(`POST`, `settings_mission_rules_force`, {
            tableInventoryPanning
        });

    $(`#addNewInventoryPlanner`).on(`click`, () => {
        $modalNewInventoryPanner.modal(`show`);
    });
}
