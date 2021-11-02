const types = {
    success: `Succès`,
    danger: `Erreur`,
    info: `Information`
}

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
 * @param {string} message
 * @param {'danger'|'success'|'info'} color
 * @param {boolean = true} remove
 */
function showBSAlert(message, color, remove = true) {
    if ((typeof message === 'string') && message) {
        const $alertContainer = $('#alerts-container');
        const $alert = $('#alert-template')
            .clone()
            .removeAttr('id')
            .addClass(`wii-alert-${color}`)
            .removeClass('d-none');

        $alert
            .find('.content')
            .html(message);

        $alert
            .find('.alert-content')
            .find('.type')
            .html('<strong>' + types[color] + '</strong>');

        $alertContainer.append($alert);

        if (remove) {
            $alert.delay(5500).fadeOut(500);

            setTimeout(() => {
                if ($alert.parent().length) {
                    $alert.remove();
                }
            }, 6000);
        }
    }
}
