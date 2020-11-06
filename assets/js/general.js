export default class Wiistock {
    static download(url) {
        let isFirefox = navigator.userAgent.includes("Firefox");

        if(isFirefox) {
            window.open(url);
        } else {
            window.location.href = url;
        }
    }
}
