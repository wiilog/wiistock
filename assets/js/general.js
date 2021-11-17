export default class Wiistock {
    static download(url) {
        let isFirefox = navigator.userAgent.includes("Firefox");

        if(isFirefox) {
            window.open(url);
        } else {
            window.location.href = url;
        }
    }

    static initialize() {
        $(document).on(`click`, `.increase-decrease-field .increase, .increase-decrease-field .decrease` , function(){
            const $button = $(this);
            const $input = $button.siblings('input').first();
            if($input.is(`[disabled], [readonly]`)) {
                return;
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
        });
    }
}
