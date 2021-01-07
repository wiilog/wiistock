$(function (){
    const $alertsFlashbagElement = $('#alerts-flashbag');
    const alertsFlashbagStr = $alertsFlashbagElement.val();
    if (alertsFlashbagStr) {
        const alertsFlashbag = JSON.parse(alertsFlashbagStr);
        if (alertsFlashbag.length > 0) {
            const displayedAlert = alertsFlashbag.join('<br/>');
            const alertColor = $alertsFlashbagElement.data('color');
            showBSAlert(displayedAlert, alertColor);
        }
    }
});

/**
 *
 * @param {string} message
 * @param {'danger'|'success'|'warning'} color
 * @param {boolean = true} remove
 */
function showBSAlert(message, color, remove = true) {
    if ((typeof message === 'string') && message) {
        const $alertContainer = $('#alerts-container');
        const $alert = $('#alert-template')
            .clone()
            .removeAttr('id')
            .addClass(`alert-${color}`)
            .removeClass('d-none')
            .addClass('d-flex');

        $alert
            .find('.content')
            .html(message);

        $alertContainer.html($alert);

        if (remove) {
            $alert
                .delay(3000)
                .fadeOut(2000);
            setTimeout(() => {
                if ($alert.parent().length) {
                    $alert.remove();
                }
            }, 5000);
        }
    }
}
