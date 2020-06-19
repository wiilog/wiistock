function initTooltips($elements) {
    $elements.each(function () {
        $(this).tooltip('dispose');
        $(this).tooltip();
    });
}

/**
 * Transform milliseconds to 'X h X min' or 'X min' or '< 1 min'
 */
function renderMillisecondsToDelay(milliseconds, type) {
    let res;

    if (type === 'display') {
        const hours = Math.floor(milliseconds / 1000 / 60 / 60);
        const minutes = Math.floor(milliseconds / 1000 / 60) % 60;
        res = (
                (hours > 0)
                    ? `${hours < 10 ? '0' : ''}${hours} h `
                    : '') +
            ((minutes === 0 && hours < 1)
                ? '< 1 min'
                : `${(hours > 0 && minutes < 10) ? '0' : ''}${minutes} min`)
    } else {
        res = milliseconds;
    }

    return res;
}
