
$(function () {
    const btnWarningHeaderMessage = document.querySelector('.btn-warning-header-message');
    const bannerWarningHeaderMessage = document.querySelector('.banner-warning-header-message');

    const cookieName = 'warning-header-message';
    const userChoice = document.cookie.includes(`${cookieName}=true`);

    if (userChoice) {
        hideWarningHeaderMessage(btnWarningHeaderMessage, bannerWarningHeaderMessage);
    }
    else {
        displayWarningHeaderMessage(btnWarningHeaderMessage, bannerWarningHeaderMessage);
    }

    btnWarningHeaderMessage.addEventListener('click', () => {
        document.cookie = `${cookieName}=true`;
        hideWarningHeaderMessage(btnWarningHeaderMessage, bannerWarningHeaderMessage);
    });
});

function hideWarningHeaderMessage(btnWarningHeaderMessage, bannerWarningHeaderMessage) {
    bannerWarningHeaderMessage.style.display = 'none';
}

function displayWarningHeaderMessage(btnWarningHeaderMessage, bannerWarningHeaderMessage) {
    bannerWarningHeaderMessage.style.display = 'flex';
    bannerWarningHeaderMessage.classList.remove('d-none');
}
