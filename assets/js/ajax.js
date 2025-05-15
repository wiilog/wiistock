import Flash, {ERROR, INFO, SUCCESS} from "./flash";
import Routing from '@app/fos-routing';
import {saveAs} from "file-saver";

export const GET = `GET`;
export const POST = `POST`;
export const PUT = `PUT`;
export const PATCH = `PATCH`;
export const DELETE = `DELETE`;

export default class AJAX {
    method;
    route;
    url;
    params;

    static GET = GET;
    static POST = POST;
    static PUT = PUT;
    static PATCH = PATCH;
    static DELETE = DELETE;


    /**
     * @param {"GET"|"POST"|"PUT"|"PATCH"|"DELETE"} method
     * @param {string} route
     * @param {{[param: string]: string|int}|{}} params
     * @returns {AJAX}
     */
    static route(method,
                 route,
                 params = {}) {
        const ajax = new AJAX();
        ajax.method = method;
        ajax.route = route;
        ajax.params = params;

        return ajax;
    }

    static url(method, url, params = {}) {
        const ajax = new AJAX();
        ajax.method = method;
        ajax.url = url;
        ajax.params = params;

        return ajax;
    }

    config(body) {
        if(!(body instanceof FormData) && (typeof body === `object` || Array.isArray(body))) {
            body = JSON.stringify(body);
        }

        let url;
        if(this.route) {
            url = Routing.generate(this.route, this.params);
        } else if(this.method === `GET` || this.method === `DELETE`) {
            url = this.url;
            let temporaryPrefix = !this.url.startsWith(`http`);
            if(temporaryPrefix) {
                if(url.charAt(0) === `/`) {
                    url = this.url.substring(1);
                }

                url = `http://localhost/${url}`;
            }

            let parser = new URL(url);
            for(let [key, value] of Object.entries(this.params)) {
                if(Array.isArray(value) || typeof value === 'object') {
                    value = JSON.stringify(value);
                }

                parser.searchParams.set(key, value);
            }

            url = parser.toString();
            if(temporaryPrefix) {
                url = url.substring(`http://localhost`.length);
            }
        } else {
            url = this.url;
        }

        const config = {
            method: this.method,
            body,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        };

        return [url, config];
    }

    raw(body) {
        const [url, config] = this.config(body);

        return fetch(url, config)
            .then(response => {
                if(response.url.endsWith(`/login`)) {
                    window.location.href = Routing.generate(`login`);
                } else {
                    return response;
                }
            })
            .catch(error => {
                Flash.serverError(error, true);
                throw error;
            });
    }

    file({body, success, error}) {
        return this.raw(body)
            .then((response) => {
                if (!response.ok) {
                    if (error) {
                        Flash.add(ERROR, error);
                    }
                    console.error('printing error');
                }
                return response.blob().then((blob) => {
                    const responseContent = response.headers.get("content-disposition")
                    if (!responseContent) {
                        console.warn('aucun fichier à télécharger');
                        return;
                    }
                    const fileName = responseContent.split("filename=")[1];
                    const cleanedFileName = fileName.replace(/^"+|"+$/g, ``);

                    saveAs(blob, cleanedFileName);
                    if (success) {
                        Flash.add(SUCCESS, success);
                    }
                });
            });
    }

    json(body) {
        const [url, config] = this.config(body);

        return fetch(url, config)
            .then(response => {
                if(response.url.endsWith(`/login`)) {
                    window.location.href = Routing.generate(`login`);
                } else {
                    return response.json();
                }
            })
            .then((json) => {
                treatFetchCallback(json);
                return json;
            })
            .catch(error => {
                Flash.serverError(error, true);
                throw error;
            });
    }

}

function treatFetchCallback(json) {
    if(json.status === 500) {
        Flash.serverError(json, true);
        return;
    }

    const message = (json.message || json.msg);
    if (json.success === false && message) {
        Flash.add(ERROR, message, true, true);
    }
    else if(json.success === true && message) {
        Flash.add(SUCCESS, message, true, true);
    }

    if(json.reload === true) {
        $.fn.dataTable
            .tables({visible: true, api: true})
            .ajax
            .reload();
    }
}

global.AJAX = AJAX;
