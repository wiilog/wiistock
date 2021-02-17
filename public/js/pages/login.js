try {
    $(document).ready(function () {
        initImagePopovers();

        let isChromium = !!window.chrome;
        let isFirefox = navigator.userAgent.includes("Firefox");

        if (!isChromium && !isFirefox) {
            showCompliantBrowserMessage();
        }
    });
}
catch (ignored) {
    showCompliantBrowserMessage();
}

function showCompliantBrowserMessage() {
    const elementsToDisplay = document.getElementsByClassName('messageChrome');
    const elementsToHide = document.getElementsByClassName('form-signin');
    if (elementsToDisplay) {
        for (let i = 0; i < elementsToDisplay.length; i++) {
            elementsToDisplay[i].classList.add('d-block');
        }
    }
    if (elementsToHide) {
        for (let i = 0; i < elementsToHide.length; i++) {
            elementsToHide[i].classList.add('d-none');
        }
    }
}
