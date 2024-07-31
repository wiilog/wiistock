export default class Cookie {

    /**
     * Return value of the cookie associated to the given name.
     * @param name
     * @returns {string}
     */
    static get(name) {
        const localCookies = getLocalCookies();
        return localCookies[name];
    }

    /**
     * Create a cookie with given name and value.
     * Encode name & value of the cookie with encodeURIComponent.
     *
     * @param {string} name Name of the cookie.
     * @param {string|number} value Value of the cookie.
     * @param {number?} days Number of day the cookie will be valid, default to 7 days.
     *
     * @return void
     */
    static save(name, value, days = 7) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));

        document.cookie = createCookieStr(
            encodeURIComponent(name),
            encodeURIComponent(value),
            {
                expires: date.toUTCString(),
                path: "/",
            }
        );
    }

    /**
     * Remove cookie with the given name
     * @param {string} name
     * @return void
     */
    static delete(name) {
        document.cookie = createCookieStr(
            encodeURIComponent(name),
            "",
            {
                expires: "Thu, 01-Jan-1970 00:00:01 GMT",
                path: "/",
                domain: window.location.host.toString(),
            }
        );
    }

}

/**
 * Return associated object according to current document.cookie string.
 * Decode name & value of the cookie with decodeURIComponent.
 * @returns {Object.<string, string>}
 */
function getLocalCookies() {
    return (document.cookie || '')
        .split(';')
        .map((cookieStr) => cookieStr.trim())
        .filter((cookieStr) => cookieStr)
        .map((cookieStr) => cookieStr.split('='))
        .reduce((acc, [name, value]) => ({
            ...acc,
            [decodeURIComponent(name)]: decodeURIComponent(value),
        }), {});
}

/**
 * Return cookie string with the given associated object.
 * @param {string} name
 * @param {string|number} value
 * @param {Object.<string, string|number>} data
 * @returns {string}
 */
function createCookieStr(name, value, data = {}) {
    // name and value of the cookie at beginning of the string
    return Object.entries({[name]: value, ...data})
        .map(([key, value]) => `${key}=${value}`)
        .join(';');
}
