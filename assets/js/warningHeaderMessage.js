
$(function () {
    const btnWarningHeaderMessage = document.querySelector('.btn-warning-header-message');
    const bannerWarningHeaderMessage = document.querySelector('.banner-warning-header-message');

    const cookieName = 'warning-header-message';
    const userChoice = document.cookie.includes(`${cookieName}=true`);

    if (userChoice) {
        hideWarningHeaderMessage(btnWarningHeaderMessage, bannerWarningHeaderMessage);
    }

    btnWarningHeaderMessage.addEventListener('click', () => {
        document.cookie = `${cookieName}=true`;
        hideWarningHeaderMessage(btnWarningHeaderMessage, bannerWarningHeaderMessage);
    });
});

function hideWarningHeaderMessage(btnWarningHeaderMessage, bannerWarningHeaderMessage) {
    btnWarningHeaderMessage.style.display = 'none';
    bannerWarningHeaderMessage.style.display = 'none';
    bannerWarningHeaderMessage.classList.remove('d-flex');
}
