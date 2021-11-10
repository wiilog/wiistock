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

            const value = parseInt($input.val()) || 0;
            if($button.hasClass('increase')){
                $input.val(value + 1);
                $input.removeClass('is-invalid');
            } else if($button.hasClass('decrease') && value >= 1) {
                $input.val(value - 1);
                $input.removeClass('is-invalid');
            } else {
                $input.val(0);
            }

            if($input.attr(`max`) < $input.val()) {
                $input.val($input.attr(`max`));
            }

            if($input.attr(`min`) > $input.val()) {
                $input.val($input.attr(`min`));
            }

            $input.trigger(`change`);
        });
    }
}
