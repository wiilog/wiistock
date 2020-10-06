$(document).ready(function() {
    let isChromium = window.chrome;
    let winNav = window.navigator;
    let vendorName = winNav.vendor;
    let isOpera = typeof window.opr !== "undefined";
    let isIEedge = winNav.userAgent.indexOf("Edge") > -1;
    let isIOSChrome = winNav.userAgent.match("CriOS");

    if (isIOSChrome) {
        // is Google Chrome on IOS
    } else if(
        isChromium === null ||
        typeof isChromium === "undefined" ||
        vendorName !== "Google Inc." ||
        isOpera !== false ||
        isIEedge !== false) {
        $('.messageChrome').addClass('d-block');
        $('.form-signin').addClass('d-none');
    }
});
