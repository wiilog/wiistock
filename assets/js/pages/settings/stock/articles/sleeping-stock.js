import EditableDatatable, {MODE_EDIT, SAVE_MANUALLY} from "@app/editatable";
import AJAX, {GET, POST} from "@app/ajax";
import Flash, {ERROR, INFO, SUCCESS} from "@app/flash";

/**
 * @param {jQuery} $container
 */
export function initializeSleepingStockSettingPage($container){
    initializeSleepingStockRequestInformations($container)
    initializeSleepingSleepingStockPlan($container)

    const sleepingStockForceSendAlertbuttonClickEvent = 'click.sleepingStockForceSendAlertbuttonClickEvent';
    $container
        .find('.sleeping-stock-force-send-alert-button')
        .off(sleepingStockForceSendAlertbuttonClickEvent)
        .on(sleepingStockForceSendAlertbuttonClickEvent, () => {
            AJAX
                .route(
                    POST,
                    'settings_sleeping_stock_plan_force_trigger',
                )
                .json()
                .then(({success, message}) => {
                    Flash.add(succes ? SUCCESS : ERROR, message)
                });
        })
}

/**
 * @param {jQuery} $container
 */
function initializeSleepingStockRequestInformations($container){
    EditableDatatable.create('#sleepingStockRequestInformations', {
        route: Routing.generate('settings_sleeping_stock_request_information_api', true),
        deleteRoute: `settings_sleeping_stock_request_information_delete`,
        mode: MODE_EDIT,
        save: SAVE_MANUALLY,
        search: false,
        paging: false,
        scrollY: false,
        scrollX: false,
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: 'deliveryRequestTemplate', title: 'Modèle de la demande de livraison', required: true},
            {data: 'buttonLabel', title: 'Libellé du bouton d’action', required: true},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            deliveryRequestTemplate: $container.find('template#article_sleepingStock_deliveryRequestTemplate').html(),
            buttonLabel: $container.find('template#article_sleepingStock_buttonLabel').html(),
        },
    });
}

/**
 * @param {jQuery} $container
 */
function initializeSleepingSleepingStockPlan($container) {
    const $sleepingStockPlanContainer = $container.find('.sleeping-stock-plan-setting');
    const typeChangeEvent = 'change.typeOfSleepingStockPlan';
    $container.find('[name="planType"]')
        .off(typeChangeEvent)
        .on(typeChangeEvent, function(event) {
            const typeId = event.target.value;
            loadSleepingSleepingStockPlan(typeId, $sleepingStockPlanContainer)
        });

    $container.find('[name="planType"]:checked')
        .trigger(typeChangeEvent)
}

/**
 * @param {int} typeId
 * @param {jQuery} $sleepingStockPlanContainer
 */
function loadSleepingSleepingStockPlan(typeId, $sleepingStockPlanContainer) {
    wrapLoadingOnActionButton($sleepingStockPlanContainer, () => (
        AJAX
            .route(
                GET,
                "settings_sleeping_stock_plan_api",
                {
                    type: typeId
                }
            )
            .json()
            .then((response) => {
                $sleepingStockPlanContainer.html(response.html);
                const $checkedFrequency = $sleepingStockPlanContainer.find('[name=frequency]:checked');
                if ($checkedFrequency.exists()) {
                    toggleFrequencyInput($checkedFrequency);
                }
            })
    ));
}
