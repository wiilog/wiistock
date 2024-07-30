import {createCookie, deleteCookie, getCookie} from "@app/cookie";

const COOKIE_NAME = 'warning-header';

$(function () {
    // Value get from the dom element
    const $btnWarningHeader = $('.btn-warning-header');
    const $bannerWarningHeader = $('.banner-warning-header');
    const warningHeaderHash = $('input[name=warning-header-hash]').val();

    // Value get from cookie
    const cookieHash = getCookie(COOKIE_NAME);

    if (!!cookieHash) {
        if (warningHeaderHash !== cookieHash) {
            // Delete cookie if hash is different, that means the warning header has been changed
            deleteCookie(COOKIE_NAME);
            displayWarningHeader($bannerWarningHeader);

        } else {
            hideWarningHeader($bannerWarningHeader);
        }

    } else {
        displayWarningHeader($bannerWarningHeader);
    }

    onClickWarningHeader($btnWarningHeader, $bannerWarningHeader, warningHeaderHash);
});

function hideWarningHeader($bannerWarningHeader) {
    $bannerWarningHeader.hide();
}

function displayWarningHeader($bannerWarningHeader) {
    $bannerWarningHeader.css('display', 'flex').removeClass('d-none');
}

function onClickWarningHeader($btnWarningHeader, $bannerWarningHeader, warningHeaderHash) {
    $btnWarningHeader.on('click', function () {
        createCookie(COOKIE_NAME, warningHeaderHash);

        hideWarningHeader($bannerWarningHeader);
    });
}
