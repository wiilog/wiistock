const SPINNER_WRAPPER_CLASS = 'spinner-border-wrapper';
export const LOADING_CLASS = 'wii-loading';

/**
 * Add a loader on the element
 * @param {"white"|"black"} color
 * @param {"small"|"normal"} size
 * @returns {jQuery}
 */
jQuery.fn.pushLoader = function(color, size = 'small') {
    const $element = $(this[0]) // This is the element

    if ($element.find(`.${SPINNER_WRAPPER_CLASS}`).length === 0) {
        const sizeClass = size === 'small' ? 'spinner-border-sm' : ''
        const $loaderWrapper = $('<div/>', {
            class: SPINNER_WRAPPER_CLASS,
            html: $('<div/>', {
                class: `spinner-border ${sizeClass} text-${color}`,
                role: 'status',
                html: $('<span/>', {
                    class: 'sr-only',
                    text: 'Loading...'
                })
            })
        });
        $element
            .append($loaderWrapper)
            .addClass(LOADING_CLASS);
    }

    return this;
};

/**
 * Add a loader on the element
 * @returns {jQuery}
 */
jQuery.fn.popLoader = function() {
    const $element = $(this[0]) // This is the element
    const $loaderWrapper = $element.find(`.${SPINNER_WRAPPER_CLASS}`)
    if ($loaderWrapper.length > 0) {
        $loaderWrapper.remove();
        $element.removeClass(LOADING_CLASS);
    }

    return this;
};

/**
 * Set status of button to 'loading' and prevent other click until first finished.
 * @param {*} $button jQuery button element
 * @param {function} action Function retuning a promise
 * @param {boolean} endLoading default to true
 */
export function wrapLoadingOnActionButton($button, action = null, endLoading = true) {
    if (!$button.hasClass(LOADING_CLASS)) {
        const loadingColor = ($button.hasClass('btn-light') || $button.hasClass('btn-link'))
            ? 'black'
            : 'white';

        $button.pushLoader(loadingColor);

        if(action) {
            action().then((success) => {
                if (endLoading || !success) {
                    $button.popLoader();
                }
            });
        }
    } else {
        showBSAlert('L\'opération est en cours de traitement', 'warning');
    }
}
