import AJAX, {GET} from "@app/ajax";
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";

$(function() {
    const $modalLaunchPreparation = $(`#modalLaunchPreparation`);

    $('.launch-preparation-button').on('click', function (){
        getOrUpdatePreparationCard($modalLaunchPreparation);
    });

    $('input[name=dateFrom], input[name=dateTo]').on('change', function (){
        getOrUpdatePreparationCard($modalLaunchPreparation);
    });

});

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
