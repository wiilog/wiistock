import 'bootstrap';
import 'select2';
import Chart from 'chart.js';
import moment from 'moment';
import 'datatables.net';
import 'datatables.net-dt/js/dataTables.dataTables';
import '@fortawesome/fontawesome-free/js/all.js';
import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
import Quill from 'quill/dist/quill.js';
import Toolbar from 'quill/modules/toolbar';
import Snow from 'quill/themes/snow';
import 'arrive';

import BrowserSupport from './support';
import Wiistock from './general';
import {LOADING_CLASS, wrapLoadingOnActionButton} from './loading';
import './tooltips';

import '../scss/app.scss';

///////////////// Main

importWiistock();
importJquery();
importMoment();
importQuill();
importRouting();
importChart();

///////////////// Functions

function importWiistock() {
    global.LOADING_CLASS = LOADING_CLASS;

    global.Wiistock = Wiistock;
    global.wrapLoadingOnActionButton = wrapLoadingOnActionButton;
}

function importJquery() {
    global.$ = global.jQuery = $;
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
    const routes = require('../../public/generated/routes.json');
    Routing.setRoutingData(routes);

    global.Routing = Routing;
}

jQuery.deepCopy = function(object) {
    return object !== undefined ? JSON.parse(JSON.stringify(object)) : object;
};

$(document).ready(() => {
    if (!BrowserSupport.input("datetime-local")) {
        const observer = new MutationObserver(function () {
            for (const input of $('input[type=datetime-local]')) {
                const $input = $(input);

                if (!$input.data("dtp-initialized")) {
                    $input.data("dtp-initialized", "true");

                    const original = $input.val();
                    const formatted = moment(original, "YYYY-MM-DDTHH:mm")
                        .format("DD/MM/YYYY HH:mm");

                    $input.attr("placeholder", "dd/mm/yyyy HH:MM")
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
});

