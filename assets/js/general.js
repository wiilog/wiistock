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
            const min = parseInt($input.attr(`min`)) || 0;
            const max = parseInt($input.attr(`max`)) || 0;

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
            if(min >= value) {
                $input.val(min);
            }

            if(max <= value) {
                $input.val(max);
            }

            $input.trigger(`change`);
        });
    }
}
