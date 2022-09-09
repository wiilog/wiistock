import '@styles/planning.scss';
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX, {POST, PUT} from "@app/ajax";
import Planning from "@app/planning";
import moment from "moment";
import Flash, {ERROR, SUCCESS} from "@app/flash";

global.callbackSaveFilter = callbackSaveFilter;
let planning = null;

$(function () {
    planning = new Planning($('.preparation-planning'), {route: 'preparation_planning_api', step: 5});
    planning.onPlanningLoad(() => {
        onPlanningLoaded(planning);
    });

    initializeFilters();
    initializePlanningNavigation();

    const $modalLaunchPreparation = $(`#modalLaunchPreparation`);

    $('.launch-preparation-button').on('click', function (){
        getPreparationLaunchForm($modalLaunchPreparation);
    });

    $('input[name=dateFrom], input[name=dateTo]').on('change', function (){
        getPreparationLaunchForm($modalLaunchPreparation);
    });

    $modalLaunchPreparation.find('.check-stock-button').html($(`<div/>`, {
        class: `d-inline-flex align-items-center`,
        html: [$(`<span/>`, {
            class: `wii-icon wii-icon-white-tick mr-2`
        }), $(`<span/>`, {
            text: `Vérifier le stock`,
        })]
    }));
});

function callbackSaveFilter() {
    if (planning) {
        planning.fetch();
    }
}

function refreshColumnHint($column) {
    const preparationCount = $column.find('.preparation-card').length;
    const preparationHint = preparationCount + ' préparation' + (preparationCount > 1 ? 's' : '');
    $column.find('.column-hint-container').html(`<span class='font-weight-bold'>${preparationHint}</span>`);
}

function initializeFilters() {
    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_PREPARATION_PLANNING);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        const planningFilters = data
            .filter((filter) => filter.field.startsWith('planning-status-'));

        if (planningFilters.length > 0) {
            planningFilters.forEach((filter) => $(`input[name=${filter.field}]`).prop('checked', true));
        } else {
            $(`.filter-checkbox`).prop('checked', true)
        }
    }, 'json');

    $('.planning-filter').on('click', function() {
        const $checkbox = $(this).find('.filter-checkbox');
        $checkbox.prop('checked', !$checkbox.is(':checked'));
    });
}

function onPlanningLoaded(planning) {
    Sortable.create('.planning-card-container', {
        acceptFrom: '.planning-card-container',
        items: '.can-drag',
        handle: '.can-drag',
    });

    const $cardContainers = planning.$container.find('.planning-card-container');

    $cardContainers
        .on('sortupdate', function (e) {
            const $origin = $(e.detail.origin.container);
            const $card = $(e.detail.item);
            const preparation = $card.data('preparation');
            const $destination = $card.closest('.planning-card-container');
            const $column = $destination.closest('.preparation-card-column');
            const date = $column.data('date');
            wrapLoadingOnActionButton($destination, () => (
                AJAX.route(PUT, 'preparation_edit_preparation_date', {date, preparation})
                    .json()
                    .then(() => {
                        refreshColumnHint($column);
                        refreshColumnHint($origin.closest('.preparation-card-column'));
                    })
            ));
        });
}

function initializePlanningNavigation() {
    $('.today-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning
                .resetBaseDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $('.decrement-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.previousDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $('.increment-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.nextDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });
}

function changeNavigationButtonStates() {
    const $todayDate = $('.today-date');
    $todayDate.prop('disabled', moment().isSame(planning.baseDate, 'day'));
}

function getPreparationLaunchForm($modal){
    $modal.modal('show');
    const dateFrom = $('input[name=dateFrom]').val();
    const dateTo = $('input[name=dateTo]').val();
    if (dateFrom && dateTo) {
        AJAX.route(POST, `planning_preparation_launching_filter`, {
            from: dateFrom,
            to: dateTo
        })
            .json()
            .then((response) => {
                const $modalContent = $modal.find('.modal-content-wrapper');
                $modalContent
                    .empty()
                    .addClass('d-none');
                if (response.success) {
                    $modalContent.removeClass('d-none');
                    $modalContent.append(response.template);

                    $modal.find('.check-stock-button').html($(`<div/>`, {
                        class: `d-inline-flex align-items-center`,
                        html: [$(`<span/>`, {
                            class: `wii-icon wii-icon-white-tick mr-2`
                        }), $(`<span/>`, {
                            text: `Vérifier le stock`,
                        })]
                    }))
                        .data('launch-preparations', "0")
                        .removeClass('btn-success')
                        .addClass('btn-primary');

                    onOrdersDragAndDropDone($modal);
                    const sortables = Sortable.create(`.available-preparations, .assigned-preparations`, {
                        acceptFrom: `.preparations-container`,
                    });

                    $(sortables).on('sortupdate', () => {
                        onOrdersDragAndDropDone($modal);
                    });
                }

                $modal.find('.add-all').on('click', function () {
                    const $preparationCards = $modal.find('.available-preparations .preparation-card');
                    const $targetContainer = $modal.find('.assigned-preparations');
                    $preparationCards
                        .detach()
                        .appendTo($targetContainer);

                    onOrdersDragAndDropDone($modal);
                });

                $modal.find('.remove-all').on('click', function () {
                    const $preparationCards = $modal.find('.assigned-preparations .preparation-card');
                    const $targetContainer = $modal.find('.available-preparations');
                    $preparationCards
                        .detach()
                        .appendTo($targetContainer);
                    onOrdersDragAndDropDone($modal);
                    $modal.find('.quantities-information-container').addClass('d-none');
                    $modal.find('.check-stock-button').html($(`<div/>`, {
                        class: `d-inline-flex align-items-center`,
                        html: [$(`<span/>`, {
                            class: `wii-icon wii-icon-white-tick mr-2`
                        }), $(`<span/>`, {
                            text: `Vérifier le stock`,
                        })]
                    }))
                        .data('launch-preparations', "0")
                        .removeClass('btn-success')
                        .addClass('btn-primary');
                });

                $modal.find('.check-stock-button').on('click', function () {
                    if ($modal.find('.check-stock-button').data('launch-preparations') === "1") {
                        $modal.find('.modal-content-wrapper .btn-primary')
                            .removeClass('.btn-primary')
                            .addClass('btn-secondary')
                            .attr("disabled", true);
                        $modal.find('.modal-content-wrapper .btn-outline-primary')
                            .removeClass('.btn-outline-primary')
                            .addClass('btn-outline-secondary')
                            .attr("disabled", true);
                    }
                    launchStockCheck($modal);
                });
            });
    }
}

function onOrdersDragAndDropDone($modal){
    const $preparationsAvailable = $modal.find('.available-preparations .preparation-card');
    const $preparationsToStart = $modal.find('.assigned-preparations .preparation-card');
    const $preparationsAvailableContainer = $modal.find('.available-preparations-counter');
    const $preparationsToStartContainer = $modal.find('.assigned-preparations-counter');
    const $submitButton = $modal.find('.check-stock-button');
    const $availableCounter = $preparationsAvailable.length;
    const $assignedCounter = $preparationsToStart.length;

    $modal.find('.quantities-information-container').addClass('d-none');
    $modal.find('.assigned-preparations').removeClass('border border-danger border-success');
    $preparationsAvailable
        .addClass('orange-card')
        .removeClass('red-card');
    $submitButton
        .data('launch-preparations', "0")
        .attr(`disabled`, !$preparationsToStart.exists());
    $preparationsAvailableContainer.html($availableCounter);
    $preparationsToStartContainer.html($assignedCounter);
    $submitButton.find(`div > span:last-child`).text("Vérifier le stock");
}

function launchStockCheck($modal) {
    const $assignedPreparations = $modal.find('.assigned-preparations');
    const $preparationCards = $assignedPreparations.find('.preparation-card');
    const $submitButton = $modal.find('.check-stock-button');
    const data = [];
    $preparationCards.each(function() {
        data.push($(this).data('preparation'));
    });
    $modal.find('.quantities-information-container').addClass('d-none');
    const $loadingContainers = $.merge($assignedPreparations, $modal.find('.check-stock-button'));

    wrapLoadingOnActionButton($loadingContainers, function() {
        $modal.find('.cancel-button').attr("disabled", true);
        return AJAX
            .route(POST, `planning_preparation_launch_check_stock`, {
                launchPreparations: $submitButton.data('launch-preparations')
            })
            .json(data)
            .then((res) => {
                if (res.success) {
                    $modal.find('.cancel-button').attr("disabled", false);
                    if (res.unavailablePreparationsId.length > 0) {
                        Flash.add(ERROR, "Votre stock est insuffisant pour démarrer cette préparation");
                        res.unavailablePreparationsId.forEach((id) => {
                            $modal.find(`[data-preparation="${id}"]`)
                                .addClass('red-card')
                                .removeClass('orange-card');
                        });
                        $modal.find('.assigned-preparations').addClass('border border-danger');
                        $submitButton.html($(`<div/>`, {
                            class: `d-inline-flex align-items-center`,
                            html: [$(`<span/>`, {
                                class: `wii-icon wii-icon-white-tick mr-2`
                            }), $(`<span/>`, {
                                text: `Vérifier le stock`,
                            })]
                        }))
                            .data('launch-preparations', "0")
                            .removeClass('btn-success')
                            .addClass('btn-primary');
                        $modal.find('.quantities-information-container').removeClass('d-none');
                        $modal.find('.quantities-information').empty();
                        $modal.find('.quantities-information').append(res.template);
                    } else if ($submitButton.data('launch-preparations') === "1") {
                        $modal.modal('hide');
                        callbackSaveFilter();
                    } else {
                        Flash.add(SUCCESS, "Le stock demandé est disponible");
                        $modal.find('.cancel-button')
                            .removeClass('btn btn-outline-primary')
                            .addClass('btn btn-outline-secondary');
                        $submitButton
                            .removeClass('btn-primary')
                            .addClass('btn-success')
                            .text("Valider le lancement")
                            .data('launch-preparations', "1");
                        $modal.find('.assigned-preparations').addClass('border border-success');
                    }
                }
            });
    });
}



