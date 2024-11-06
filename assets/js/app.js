import 'bootstrap';
import 'select2';
import Chart from 'chart.js';
import moment from 'moment';
import 'datatables.net';
import 'datatables.net-dt/js/dataTables.dataTables';
import 'datatables.net-colreorder';
import 'leaflet';
import 'leaflet.smooth_marker_bouncing';
import 'leaflet.polyline.snakeanim';
import 'leaflet.markercluster'
import 'leaflet-ant-path';
import intlTelInput from 'intl-tel-input';
import '@fortawesome/fontawesome-free/js/all.js';
import Routes from "@generated/routes.json";
import Routing from '@app/fos-routing';
import Quill from 'quill';
import Toolbar from 'quill/modules/toolbar';
import Snow from 'quill/themes/snow';
import 'arrive';
import firebase from "firebase/app";
import "firebase/messaging";
import "./flash";
import "./ajax";
import "./utils";
import Camera from "@app/camera"

import BrowserSupport from './support';
import Wiistock from './general';
import Modal from './modal';
import WysiwygManager from './wysiwyg-manager';
import {LOADING_CLASS, wrapLoadingOnActionButton} from './loading';
import {showAndRequireInputByType} from './show-hide-filed';

import '../scss/app.scss';

import './tooltips';
import './select2';
import Form from "./form";
import {extendsDateSort, initDataTable, initSearchDate, initDatatablePlugin} from "@app/datatable";
import {initFieldModes} from "@app/field-modes";

export const $document = $(document);

///////////////// Main

$(document).on('click', '.dropdown-menu.keep-open', function (e) {
    e.stopPropagation();
});

importRouting();
importWiistock();
importForm();
importJquery();
importMoment();
importQuill();
importChart();
importLeaflet();
importIntlTelInput();
importFirebase();
importCamera();
importDatatable();
importFieldModes();

///////////////// Functions

function importWiistock() {
    global.LOADING_CLASS = LOADING_CLASS;
    global.showAndRequireInputByType = showAndRequireInputByType; // TODO WIIS-12102
    global.Wiistock = Wiistock;
    global.Modal = Modal;
    global.wrapLoadingOnActionButton = wrapLoadingOnActionButton;
    global.WysiwygManager = WysiwygManager;

    Wiistock.initialize();
}

function importForm() {
    // TODO remove when all js will be in asset
    global.Form = Form;
}

function importCamera() {
    global.Camera = Camera;
}

function importFieldModes() {
    initFieldModes();
}

function importDatatable() {
    initDatatablePlugin();
    // TODO remove when all js will be in asset
    global.initDataTable = initDataTable;
    global.extendsDateSort = extendsDateSort;
    global.initSearchDate = initSearchDate;
}

function importJquery() {
    global.$ = global.jQuery = $;

    jQuery.fn.exists = function() {
        return this.length !== 0;
    }

    const oldAttr = jQuery.fn.attr;
    jQuery.fn.attr = function () {
        const [name] = arguments;

        // check to see if it's the special case you're looking for
        if (!name) {
            const result = {};
            for(const {name, value} of this[0].attributes) {
                result[name] = value;
            }
            return result;
        } else {
            return oldAttr.apply(this, arguments);
        }
    };

    jQuery.extend({
        isValidSelector: function(selector) {
            if (typeof(selector) !== 'string') {
                return false;
            }
            try {
                var $element = $(selector);
            } catch(error) {
                return false;
            }
            return true;
        }
    });
}

function importIntlTelInput() {
    global.intlTelInput = intlTelInput;
}

function importFirebase() {
    const firebaseConfig = {
        apiKey: "AIzaSyArpJAngzyhm_XHmYRc-r1dRzauQfL1y50",
        authDomain: "follow-gt.firebaseapp.com",
        projectId: "follow-gt",
        storageBucket: "follow-gt.appspot.com",
        messagingSenderId: "217220633913",
        appId: "1:217220633913:web:ec324be7663f1ba1e704e8",
        measurementId: "G-YR5FK3KFQT"
    };
    // Initialize Firebase
    firebase.initializeApp(firebaseConfig);

    try {
        global.FCM = firebase.messaging();
    } catch(ignored) {
        console.error(`Failed to instantiate FCM`);
    }
}

function importLeaflet() {
    L.Icon.Default.imagePath = '/build/vendor/leaflet/images/'
    global.Leaflet = L;
}

function importChart() {
    global.Chart = Chart;
}

function importMoment() {
    global.moment = moment;
}

function importQuill() {
    Quill.register({
        'modules/toolbar.js': Toolbar,
        'themes/snow.js': Snow,
    });

    global.Quill = Quill;
}

function importRouting() {
    Routing.setRoutingData(Routes);
    global.Routing = Routing;
}

export const NO_GROUPING = 1;
export const GROUP_EVERYTHING = 2;
export const GROUP_WHEN_NEEDED = 3;

Array.prototype.keymap = function(callable, grouping = NO_GROUPING) {
    return keymap(this, callable, grouping);
};

jQuery.fn.keymap = function(callable, grouping = NO_GROUPING) {
    return keymap(this, callable, grouping);
};

export function keymap(array, callable, grouping = NO_GROUPING) {
    const values = {};
    for(const input of array) {
        const [key, value] = callable(input);

        if(grouping === NO_GROUPING) {
            values[key] = value;
        } else if(grouping === GROUP_EVERYTHING) {
            if(!values[key]) {
                values[key] = [];
            }

            values[key].push(value);
        } else if(grouping === GROUP_WHEN_NEEDED) {
            if(values[key] === undefined) {
                values[key] = {__single_value: value};
            } else if(values[key].__single_value !== undefined) {
                values[key] = [values[key].__single_value, value];
            } else {
                values[key].push(value);
            }
        }
    }
    if(grouping === GROUP_WHEN_NEEDED) {
        for(const [key, value] of Object.entries(values)) {
            values[key] = value.__single_value !== undefined ? value.__single_value : value;
        }
    }

    return values;
}

jQuery.deepEquals = function (x, y) {
    if (x === y) {
        return true;
    } else if ((typeof x == "object" && x != null) && (typeof y == "object" && y != null)) {
        if (Object.keys(x).length !== Object.keys(y).length) {
            return false;
        }

        for (const prop in x) {
            if (y.hasOwnProperty(prop)) {
                if (!jQuery.deepEquals(x[prop], y[prop])) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    } else {
        return false;
    }
}

jQuery.deepCopy = function(object) {
    return object !== undefined ? JSON.parse(JSON.stringify(object)) : object;
};

jQuery.fn.display = function(hide = false) {
    this.removeClass('d-none');
    if (hide) {
        this.addClass('d-none');
    }
    return this;
};

jQuery.mobileCheck = function() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
        || window.screen.width <= 992;
};

jQuery.capitalize = function(string) {
    if (typeof string !== `string`) return ``;
    return string.charAt(0).toUpperCase() + string.slice(1);
};

$(document).ready(() => {
    //custom datetimepickers for firefox
    if (!BrowserSupport.input("datetime-local")) {
        const observer = new MutationObserver(function () {
            for (const input of $('input[type=datetime-local]')) {
                const $input = $(input);

                if (!$input.data("dtp-initialized")) {
                    $input.data("dtp-initialized", "true");

                    const original = $input.val();
                    const formatted = moment(original, "YYYY-MM-DDTHH:mm")
                        .format("DD/MM/YYYY HH:mm");

                    $input.attr("placeholder", "jj/mm/aaaa --:--")
                    $input.val(formatted);
                    $input.datetimepicker({
                        format: "DD/MM/YYYY HH:mm"
                    });
                }
            }
        });

        observer.observe(document, {
            attributes: false,
            childList: true,
            characterData: false,
            subtree: true
        });
    }

    WysiwygManager.initializeWYSIWYG($(document));
});
