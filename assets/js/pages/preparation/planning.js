import '@styles/planning.scss';
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX, {PUT} from "@app/ajax";
import Planning from "@app/planning";
import moment from "moment";

global.callbackSaveFilter = callbackSaveFilter;
let planning = null;

$(function () {
    planning = new Planning($('.preparation-planning'), {route: 'preparation_planning_api', step: 5});
    planning.onPlanningLoad((event) => {
        onPlanningLoaded(planning);
    });

    initializeFilters();
    initializePlanningNavigation();

    const $modalLaunchPreparation = $(`#modalLaunchPreparation`);

    $('.launch-preparation-button').on('click', function (){
        getOrUpdatePreparationCard($modalLaunchPreparation);
    });

    $('input[name=dateFrom], input[name=dateTo]').on('change', function (){
        getOrUpdatePreparationCard($modalLaunchPreparation);
    });
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

function onPlanningLoaded(planning){
    Sortable.create('.planning-card-container', {
        placeholderClass: 'placeholder-container',
        forcePlaceholderSize: true,
        placeholder: `
            <div class="placeholder-container">
                <div class="placeholder"></div>
            </div>
        `,
        acceptFrom: '.planning-card-container',
        items: '.can-drag',
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
    const $decrementDate = $('.decrement-date');
    const $todayDate = $('.today-date');

    $todayDate.prop('disabled', moment().isSame(planning.baseDate, 'day'));
    $decrementDate.prop('disabled', moment().isSame(planning.baseDate, 'day'));
}

function getOrUpdatePreparationCard(modal){
    modal.modal('show');
    AJAX.route(`POST`, `planning_preparation_launching_filter`, {from: $('input[name=dateFrom]').val(), to: $('input[name=dateTo]').val()})
        .json()
        .then((response) => {
            modal.find('.preparations-container').empty();
            modal.find('.preparations-container').addClass('d-none');
            if(response.success) {
                modal.find('.preparations-container').removeClass('d-none');
                modal.find('.preparations-container').append(response.template);

                onOrdersDragAndDropDone(modal);
                const sortables = Sortable.create(`.available-preparations, .assigned-preparations`, {
                    acceptFrom: `.preparations`,
                });

                $(sortables).on('sortupdate', () => {
                    onOrdersDragAndDropDone(modal);
                })
            }

            modal.find('.add-all').on('click', function (){
                const $preparationCards = modal.find('.available-preparations .preparation-card-container');
                const $targetContainer = modal.find('.assigned-preparations');
                $preparationCards
                    .detach()
                    .appendTo($targetContainer);

                onOrdersDragAndDropDone(modal);
            });

            modal.find('.remove-all').on('click', function (){
                const $preparationCards = modal.find('.assigned-preparations .preparation-card-container');
                const $targetContainer = modal.find('.available-preparations');
                $preparationCards
                    .detach()
                    .appendTo($targetContainer);
                onOrdersDragAndDropDone(modal);
                modal.find('.quantities-information-container').addClass('d-none');
            });

            modal.find('.check-stock-button').on('click', function (){
                const $preparationCards = modal.find('.assigned-preparations .preparation-card');
                const $submitButton = modal.find('.check-stock-button');
                const data = [];
                $preparationCards.each(function() {
                    data.push($(this).data('preparation'));
                });
                modal.find('.quantities-information-container').addClass('d-none');
                wrapLoadingOnActionButton(modal.find('.assigned-preparations'), () => {
                    return new Promise((resolve) => {
                        wrapLoadingOnActionButton(modal.find('.check-stock-button'), () => {
                            return AJAX
                                .route(`POST`, `planning_preparation_launch_check_stock`, {launchPreparations: $submitButton.data('launch-preparations')})
                                .json(data)
                                .then((res) => {
                                    if(res.success) {
                                        if(res.unavailablePreparationsId.length > 0){
                                            showBSAlert("Votre stock est insuffisant pour démarrer cette préparation", "danger");
                                            res.unavailablePreparationsId.forEach((id) => {
                                                modal.find(`[data-preparation="${id}"]`).addClass('red-card');
                                                modal.find(`[data-preparation="${id}"]`).removeClass('orange-card');
                                            });
                                            modal.find('.assigned-preparations').addClass('border border-danger');
                                            $submitButton.text("Vérifier le stock");
                                            $submitButton.data('launch-preparations', "0");
                                            modal.find('.quantities-information-container').removeClass('d-none');
                                            modal.find('.quantities-information').empty();
                                            modal.find('.quantities-information').append(res.template);
                                        } else if($submitButton.data('launch-preparations') === "1"){
                                            modal.modal('hide');
                                            callbackSaveFilter();
                                        } else {
                                            showBSAlert("Le stock demandé est disponible", "success");
                                            $submitButton.text("Lancer les préparations");
                                            $submitButton.data('launch-preparations', "1");
                                            modal.find('.assigned-preparations').addClass('border border-success');
                                        }
                                        resolve();
                                    }
                                });
                        });
                    });
                })
            });
        });
}

function onOrdersDragAndDropDone(modal){
    const $preparationsAvailable = modal.find('.available-preparations .preparation-card');
    const $preparationsToStart = modal.find('.assigned-preparations .preparation-card');
    const $preparationsAvailableContainer = modal.find('.available-preparations-counter');
    const $preparationsToStartContainer = modal.find('.assigned-preparations-counter');
    const $submitButton = modal.find('.check-stock-button');
    const $availableCounter = $preparationsAvailable.length;
    const $assignedCounter = $preparationsToStart.length;

    modal.find('.quantities-information-container').addClass('d-none');
    modal.find('.assigned-preparations').removeClass('border border-danger border-success');
    $preparationsAvailable.addClass('orange-card');
    $preparationsAvailable.removeClass('red-card');
    $submitButton.data('launch-preparations', "0");
    $submitButton.attr(`disabled`, !$preparationsToStart.exists());
    $preparationsAvailableContainer.empty().append($availableCounter);
    $preparationsToStartContainer.empty().append($assignedCounter);
    if(!$preparationsToStart.exists()) {
        $submitButton.text("Lancer les préparations");
    } else {
        $submitButton.text("Vérifier le stock");
    }
}


