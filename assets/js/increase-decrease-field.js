export default class IncreaseDecreaseField {
    static initialize() {
        const $increaseDecreaseContainer = $('.increase-decrease-field');
        initializeInput($increaseDecreaseContainer);

        $(document).arrive(`.increase-decrease-field`, function() {
            const $increaseDecreaseContainer = $(this);
            initializeInput($increaseDecreaseContainer);
        });
    }
}

function initializeInput($increaseDecreaseContainers) {
    $increaseDecreaseContainers.each(function () {
        const $increaseDecreaseContainer = $(this);
        const $increaseDecreaseInput = $increaseDecreaseContainer.find('input');
        resetIncreaseDecreaseButtons($increaseDecreaseInput);

        $increaseDecreaseInput
            .on('change keyup', function () {
                resetIncreaseDecreaseButtons($(this));
            });

        $increaseDecreaseContainer
            .find('.increase, .decrease, input')
            .on('click', function() {
                onIncreaseDecreaseButtonClicked($(this));
            });
    });
}

function resetIncreaseDecreaseButtons($input) {
    const minInt = parseInt($input.attr(`min`));
    const min = !isNaN(minInt) ? minInt : null;

    const maxInt = parseInt($input.attr(`max`));
    const max = !isNaN(maxInt) ? maxInt : null;

    const value = parseInt($input.val()) || 0;

    $input.parent().find(`.decrease`).prop(`disabled`, value === min);
    $input.parent().find(`.increase`).prop(`disabled`, value === max);
}

function onIncreaseDecreaseButtonClicked($button) {
    const $input = $button.siblings('input').first();
    if($input.is(`[disabled], [readonly]`)) {
        return;
    }

    if($button.is(`input[name=quantity]`) && $button.hasClass(`is-invalid`)) {
        $button.removeClass('is-invalid');
    }

    let value = parseInt($input.val()) || 0;
    const minInt = parseInt($input.attr(`min`));
    const maxInt = parseInt($input.attr(`max`));
    const min = !isNaN(minInt) ? minInt : null
    const max = !isNaN(maxInt) ? maxInt : null

    if($button.hasClass('increase')){
        $input.val(value + 1);
        $input.removeClass('is-invalid');
    } else if($button.hasClass('decrease') && value >= 1) {
        $input.val(value - 1);
        $input.removeClass('is-invalid');
    } else {
        $input.val(0);
    }

    value = parseInt($input.val()) || 0;
    if(min !== null && min >= value) {
        $input.val(min);
    }

    if(max !== null && max <= value) {
        $input.val(max);
    }

    $input.trigger(`change`);
}
