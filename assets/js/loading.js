import Flash, {INFO} from "@app/flash";

const SPINNER_WRAPPER_CLASS = 'spinner-border-wrapper';
export const LOADING_CLASS = 'wii-loading';
export const MULTIPLE_LOADING_CLASS = 'wii-multiple-loading';

/**
 * Add a loader on the element
 * @param {"white"|"black"|"primary"} color
 * @param {"small"|"normal"} size
 * @returns {jQuery}
 */
jQuery.fn.pushLoader = function(color, size = 'small') {
    const $element = $(this[0]) // This is the element

    if ($element.find(`.${SPINNER_WRAPPER_CLASS}`).length === 0) {
        const sizeClass = size === 'small' ? 'spinner-border-sm' : '';
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
    $element.removeClass(LOADING_CLASS);
    if ($loaderWrapper.length > 0) {
        $loaderWrapper.remove();
    }

    return this;
};

/**
 * Set status of button to 'loading' and prevent other click until first finished.
 * @param {jQuery|Array<jQuery>} $loaderContainers jQuery button element
 * @param {function} action Function returning a promise
 * @param {boolean} endLoading default to true
 */
export function wrapLoadingOnActionButton($loaderContainers, action = null, endLoading = true) {
    $.each($loaderContainers, function() {
        const $loaderContainer = $(this);
        if (!$loaderContainer.hasClass(LOADING_CLASS) || $loaderContainer.hasClass(MULTIPLE_LOADING_CLASS)) {
            const loadingColor = (
                $loaderContainer.data('loader-color') ? $loaderContainer.data('loader-color') :
                ($loaderContainer.hasClass('btn-light') || $loaderContainer.hasClass('btn-link')) ? 'black' :
                'white'
            );
            const loadingSize = $loaderContainer.data('loader-size') || 'small';

            $loaderContainer.pushLoader(loadingColor, loadingSize);
        }
        else {
            Flash.add(INFO, 'L\'opération est en cours de traitement');
            throw new Error('Operation in progress...');
        }
    });

    if (action) {
        action()
            .then((success) => {
                if (endLoading || !success) {
                    popLoaderForMultipleContainer($loaderContainers);
                }
            })
            .catch(() => {
                popLoaderForMultipleContainer($loaderContainers);
            });
    }
}

function popLoaderForMultipleContainer($containers) {
    $.each($containers, function() {
        const $loaderContainer = $(this);
        $loaderContainer.popLoader();
    });
}
