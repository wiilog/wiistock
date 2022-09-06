try {
    $(document).ready(function () {
        initImagePopovers();

        //for testing purposes allow logging in if the allow GET param is set to any
        const params = GetRequestQuery();
        if(params.allow !== `any`) {
            const isChromium = !!window.chrome;
            const isFirefox = navigator.userAgent.includes("Firefox");

            if (!isChromium && !isFirefox) {
                showCompliantBrowserMessage();
            }
        }
    });
}
catch (ignored) {
    showCompliantBrowserMessage();
}

function showCompliantBrowserMessage() {
    const elementsToDisplay = document.getElementsByClassName('chrome-message');
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
