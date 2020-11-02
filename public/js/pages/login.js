$(document).ready(function() {
    initImagePopovers();

    let isChromium = !!window.chrome;
    let isFirefox = navigator.userAgent.includes("Firefox");

    if(!isChromium && !isFirefox) {
        $('.messageChrome').addClass('d-block');
        $('.form-signin').addClass('d-none');
    }
});
