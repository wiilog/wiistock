import Sortable from "@app/sortable";
import '@styles/pages/preparation/planning.scss';

$(function () {
    $('[data-wii-planning]').on('planning-loaded', function() {
        const $modalLaunchPreparation = $(`#modalLaunchPreparation`);

        $('.launch-preparation-button').on('click', function (){
            getOrUpdatePreparationCard($modalLaunchPreparation);
        });

        $('input[name=dateFrom], input[name=dateTo]').on('change', function (){
            getOrUpdatePreparationCard($modalLaunchPreparation);
        });

        const $cardColumns = $('.preparation-card-column');

        toggleLoaderState($cardColumns);

        Sortable.create(`.can-drag`, {
            placeholderClass: 'placeholder',
            acceptFrom: false,
        });

        Sortable.create(`.preparation-card-column`, {
            placeholderClass: 'placeholder',
            acceptFrom: '.can-drag',
        });

        $cardColumns.on('sortupdate', function (e) {
            const $destination = $(e.detail.destination.container).find('.card-container');
            const $currentElement = $(e.detail.item)[0].outerHTML;
            const $element = $(`<div class="can-drag">${$currentElement}</div>`);

            const preparation = $(e.detail.item).data('preparation');
            const date = $destination.parents('.preparation-card-column').data('date');
            $(e.detail.item).remove();
            toggleLoaderState($destination.parents('.preparation-card-column'));
            AJAX.route('PUT', 'preparation_edit_preparation_date', {date, preparation})
                .json()
                .then((response) => {
                    console.log(response);
                    $element.appendTo($destination);

                    Sortable.create(`.can-drag`, {
                        placeholderClass: 'placeholder',
                        acceptFrom: false,
                    });
                    toggleLoaderState($destination.parents('.preparation-card-column'));
                });
        })
    });
});


function toggleLoaderState($elements) {
    const $loaderContainers = $elements.find('.loader-container');
    const $cardContainers = $elements.find('.card-container');
    $loaderContainers.each(function() {
        $(this).toggleClass('d-none');
    });
    $cardContainers.each(function() {
        $(this).toggleClass('d-none');
    });
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
            });

        });
}


function onOrdersDragAndDropDone(modal){
    const $preparationsAvailable = modal.find('.available-preparations .preparation-card');
    const $preparationsToStart = modal.find('.assigned-preparations .preparation-card');
    const $preparationsAvailableContainer = modal.find('.available-preparations-counter');
    const $preparationsToStartContainer = modal.find('.assigned-preparations-counter');
    const $submitButton = modal.find('.submit-button');
    const $availableCounter = $preparationsAvailable.length;
    const $assignedCounter = $preparationsToStart.length;

    $submitButton.attr(`disabled`, !$preparationsToStart.exists());
    $preparationsAvailableContainer.empty().append($availableCounter);
    $preparationsToStartContainer.empty().append($assignedCounter);
    //if($preparationsToStart.exists() && isStockValid(modal)) {
    if($preparationsToStart.exists()) {
        $submitButton.text("Lancer les préparations");
    } else {
        $submitButton.text("Vérifier le stock");
    }
}

/*AJAX
.route(`POST`, `planning_preparation_launch_check_stock`, data)
.json()
.then((res) => {
    if(res.success) {
        const $quantitiesInformationContainer = modal.element.find('.quantities-information-container');
        const $quantitiesInformation = $quantitiesInformationContainer.find('.quantities-information');
        const $orderToStartContainer = modal.element.find('.assigned-preparations');
        const $allOrdersContainer = modal.element.find('.available-preparations, .assigned-preparations');

        $allOrdersContainer.find('.order')
            .removeClass('available')
            .removeClass('unavailable');

        for(const unavailableOrder of res.unavailableOrders) {
            $orderToStartContainer.find(`.order[data-id="${unavailableOrder}"]`).addClass('unavailable');
        }
        $orderToStartContainer.find('.order:not(.unavailable)').addClass('available');
        $quantitiesInformation.empty();

        const quantityErrors = res.availableBoxTypeData.filter((boxTypeData) => (
            boxTypeData.orderedQuantity > boxTypeData.availableQuantity
        ));

        if(quantityErrors.length > 0) {
            $quantitiesInformationContainer.removeClass('d-none');
        } else {
            $quantitiesInformationContainer.addClass('d-none');
        }

        for(const boxTypeData of quantityErrors) {
            $quantitiesInformation.append(`
                <label class="ml-2">
                    <strong>${boxTypeData.name}</strong> : Quantité commandée <strong>${boxTypeData.orderedQuantity}</strong> - disponible en stock <strong>${boxTypeData.availableQuantity}</strong> en propriété <strong>${boxTypeData.client}</strong>
                </label>
            `);
        }

        onOrdersDragAndDropDone(modal);
    }
});*/
