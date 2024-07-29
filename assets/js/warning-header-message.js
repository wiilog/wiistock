$(function() {
    const $btnWarningHeaderMessage = $('.btn-warning-header-message');
    const $bannerWarningHeaderMessage = $('.banner-warning-header-message');

    const cookieName = 'warning-header-message';
    const userChoice = document.cookie.includes(`${cookieName}=true`);

    if (userChoice) {
        hideWarningHeaderMessage($btnWarningHeaderMessage, $bannerWarningHeaderMessage);
    } else {
        displayWarningHeaderMessage($btnWarningHeaderMessage, $bannerWarningHeaderMessage);
    }

    $btnWarningHeaderMessage.on('click', function() {
        const expirationDays = 7;
        const expirationDate = new Date();
        expirationDate.setDate(expirationDate.getDate() + expirationDays);
        document.cookie = `${cookieName}=true; expires=${expirationDate.toUTCString()}; path=/`;
        hideWarningHeaderMessage($btnWarningHeaderMessage, $bannerWarningHeaderMessage);
    });
});

function hideWarningHeaderMessage($btnWarningHeaderMessage, $bannerWarningHeaderMessage) {
    $bannerWarningHeaderMessage.hide();
}

function displayWarningHeaderMessage($btnWarningHeaderMessage, $bannerWarningHeaderMessage) {
    $bannerWarningHeaderMessage.css('display', 'flex').removeClass('d-none');
}
