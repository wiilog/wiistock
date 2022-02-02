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
        Flash.add(color, message, remove);
    }
}
