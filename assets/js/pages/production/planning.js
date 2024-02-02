import '@styles/planning.scss';
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX, {POST, PUT} from "@app/ajax";
import Planning from "@app/planning";
import Form from "@app/form";
import Modal from "@app/modal";
import moment from "moment";

global.callbackSaveFilter = callbackSaveFilter;
global.openModalUpdateProductionRequestStatus = openModalUpdateProductionRequestStatus;

const $modalUpdateProductionRequestStatus = $(`#modalUpdateProductionRequestStatus`);

let planning = null;

$(function () {
    planning = new Planning($(`.production-request-planning`), {route: `production_request_planning_api`, baseDate: moment().startOf(`isoWeek`)});
    planning.onPlanningLoad(() => {
        onPlanningLoaded(planning);
    });

    initializeFilters();
    initializePlanningNavigation();
});

function callbackSaveFilter() {
    if (planning) {
        planning.fetch();
    }
}

function refreshColumnHint($column) {
    const productionRequestCount = $column.find(`.preparation-card`).length;
    const productionRequestHint = `${productionRequestCount} demande${productionRequestCount > 1 ? `s` : ``}`;
    $column.find(`.column-hint-container`).html(`<span class='font-weight-bold'>${productionRequestHint}</span>`);
}

function initializeFilters() {
    const $filtersContainer = $(`.filters-container`);

    Select2Old.init($filtersContainer.find(`.filter-select2[name=multipleTypes]`), `Types`);
    getUserFiltersByPage(PAGE_PRODUCTION_PLANNING);
}

function onPlanningLoaded(planning) {
    const $navigationButtons = $(`.previous-week, .next-week`);

    Sortable.create(`.planning-card-container`, {
        acceptFrom: `.planning-card-container`,
        items: `.can-drag`,
        handle: `.can-drag`,
    });

    Sortable.create($navigationButtons, {
        acceptFrom: `.planning-card-container`,
        items: `.can-drag`,
        handle: `.can-drag`,
    });

    const $cardContainers = planning.$container.find(`.planning-card-container`);

    $navigationButtons.removeClass(`d-none`);

    $navigationButtons.on(`sortenter`, function (e) {
        const $button = $(e.target);

        $button.trigger(`click`);
        $button.find(`.placeholder-container`).remove();
    });

    $navigationButtons.on(`sortleave`, function (e) {
        $navigationButtons.find(`.placeholder-container`).remove();
    })

    $navigationButtons.on(`sortupdate`, function (e) {
        const $button = $(e.target);
        if($button.is(`.previous-week, .next-week`)) {
            $button.find(`.planning-card`).remove();
        }

        $button.find(`.placeholder-container`).remove();
    });

    $cardContainers
        .on(`sortupdate`, function (e) {
            $navigationButtons.find(`.placeholder-container`).remove();

            const $origin = $(e.detail.origin.container);
            const $card = $(e.detail.item);
            const productionRequest = $card.data(`production-request-id`);
            const $destination = $card.closest(`.planning-card-container`);
            const $column = $destination.closest(`.production-request-card-column`);
            const date = $column.data(`date`);

            const order = Array.from($(e.target).find(`.planning-card`)).map((card) => $(card).data(`production-request-id`));

            const currentProductionRequestIndex = order.indexOf(productionRequest);
            const previousProductionRequest = order[currentProductionRequestIndex - 1] || null;
            const nextProductionRequest = order[currentProductionRequestIndex + 1] || null;

            const stringifiedOrder = JSON.stringify([previousProductionRequest, nextProductionRequest]);

            wrapLoadingOnActionButton($destination, () => (
                AJAX.route(PUT, `production_request_planning_update_expected_at`, {productionRequest, date, order: stringifiedOrder})
                    .json()
                    .then(() => {
                        $(`.tooltip`).tooltip(`hide`);
                        refreshColumnHint($column);
                        refreshColumnHint($origin.closest(`.production-request-card-column`));
                        planning.fetch();
                    })
            ));
        });
}

function initializePlanningNavigation() {
    $(`.today-date`).on(`click`, function () {
        wrapLoadingOnActionButton($(this), () => (
            planning
                .resetBaseDate(true)
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $(document).on(`click`, `.decrement-date, .previous-week`, function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.previousWeek()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $(document).on(`click`, `.increment-date, .next-week`, function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.nextWeek()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });
}

function changeNavigationButtonStates() {
    $(`.today-date`)
        .prop(`disabled`, moment().week() === planning.baseDate.week());
}

function openModalUpdateProductionRequestStatus($container){
    const productionRequest = $container.closest(`a`).data(`production-request-id`);
    Form.create($modalUpdateProductionRequestStatus, {clearOnOpen: true})
        .onOpen(() => {
            Modal.load(`production_request_planning_update_status_content`,
                {
                    productionRequest,
                },
                $modalUpdateProductionRequestStatus,
                $modalUpdateProductionRequestStatus.find(`.modal-body`)
            );
        })
        .submitTo(POST, `production_request_planning_update_status`, {
            routeParams: {
                productionRequest,
            },
            success: () => {
                planning.fetch();
            }
        });

    $modalUpdateProductionRequestStatus.modal(`show`);
}

