function todo() { //TODO: remove todo
    console.error("To do");
}

let currentChartsFontSize;
let fontSizeYAxes;

const ONGOING_PACK = 'ongoing_packs';
const DAILY_ARRIVALS = 'daily_arrivals';
const LATE_PACKS = 'late_packs';
const CARRIER_TRACKING = 'carrier_tracking';
const DAILY_ARRIVALS_AND_PACKS = 'daily_arrivals_and_packs';
const RECEIPT_ASSOCIATION = 'receipt_association';
const WEEKLY_ARRIVALS_AND_PACKS = 'weekly_arrivals_and_packs';
const ENTRIES_TO_HANDLE = 'entries_to_handle';
const PACK_TO_TREAT_FROM = 'pack_to_treat_from';
const DROPPED_PACKS_DROPZONE = 'dropped_packs_dropzone';

$(function() {
    Chart.defaults.global.defaultFontFamily = 'Myriad';
    Chart.defaults.global.responsive = true;
    Chart.defaults.global.maintainAspectRatio = false;
    currentChartsFontSize = calculateChartsFontSize();
    fontSizeYAxes = currentChartsFontSize * 0.5;
});

const creators = {
    [ONGOING_PACK]: createOngoingPackElement,
    [CARRIER_TRACKING]: createCarrierTrackingElement,
    [DAILY_ARRIVALS]: [createSimpleChart, {route: `get_arrival_um_statistics`, variable: `chartArrivalUm`}],
    [LATE_PACKS]: createLatePacksElement,
    [DAILY_ARRIVALS_AND_PACKS]: createSimpleChart,
    [RECEIPT_ASSOCIATION]: [createSimpleChart, {route: `get_asso_recep_statistics`, variable: `chartAssoRecep`}],
    [WEEKLY_ARRIVALS_AND_PACKS]: createSimpleChart,
    [ENTRIES_TO_HANDLE]: createEntriesToTreatElement,
    [PACK_TO_TREAT_FROM]: [createSimpleChart, {cssClass: 'multiple'}],
    [DROPPED_PACKS_DROPZONE]: createSimpleChart,
};

/**
 *
 * @param {jQuery} $container
 * @param {string} meterKey
 * @param {*} data
 * @return {boolean}
 */
function renderComponent(meterKey, $container, data) {
    $container.empty();

    if(!creators[meterKey]) {
        console.error(`No creator function for ${meterKey} key.`);
        return false;
    } else {
        const callback = creators[meterKey];
        let $element;
        if(Array.isArray(callback)) {
            const fn = callback[0];
            const arguments = callback.slice(1);
            $element = fn.apply(null, [data, ...arguments]);
        } else {
            $element = callback(data);
        }

        if($element) {
            $container.html($element);
            const isCardExample = $container.parents('#modalComponentTypeSecondStep').length > 0;
            const $canvas = $element.find('canvas');
            const $table = $element.find('table');
            if($canvas.length > 0) {
                if(!$canvas.hasClass('multiple')) {
                    createAndUpdateSimpleChart(
                        $canvas,
                        null,
                        data,
                        false,
                        isCardExample
                    );
                } else {
                    createAndUpdateMultipleCharts($canvas, null, data, false, true, isCardExample);
                }
            } else if($table.length > 0) {
                if($table.hasClass('retards-table')) {
                    loadLatePacks($table, data);
                }
            }
        }

        return !!$element;
    }
}

function createTooltip(text) {
    if(mode === MODE_EDIT) {
        return ``;
    } else {
        return `
            <div class="points has-tooltip" title="${text || ""}">
                <i class="fa fa-question ml-1"></i>
            </div>
        `;
    }
}

function createEntriesToTreatElement(data) {
    if(!data) {
        console.error(`Invalid data for entries element.`);
        return false;
    }
    data.chartData.forEach((arrayToUnfold, initialKey) => {
        const key = Object.keys(arrayToUnfold)[0];
        data.chartData[key] = arrayToUnfold[key];
        delete data.chartData[initialKey];
    });
    const $graph = createSimpleChart(data, {route: null, variable: null, cssClass: 'multiple'})[0].outerHTML;
    const $firstComponent = createOngoingPackElement({
        title: 'Nombres de lignes à traiter',
        tooltip: data.linesCountTooltip,
        count: data.count
    })[0].outerHTML;
    const $secondComponent = createOngoingPackElement({
        title: 'Prochain emplacement à traiter',
        tooltip: data.nextLocationTooltip,
        count: data.nextLocation
    })[0].outerHTML;
    return $(`
        <div class="row">
            <div class="col-8 pr-1">
                ${$graph}
            </div>
            <div class="col-4 pl-1">
                <div class="row h-100">
                    <div class="col-12 mb-2">${$firstComponent}</div>
                    <div class="col-12">${$secondComponent}</div>
                </div>
            </div>
        </div>
    `)


}

/**
 * @param {*} data
 * @return {boolean|jQuery}
 */
function createLatePacksElement(data) {
    if(!data) {
        console.error(`Invalid data for late packs element.`);
        return false;
    }

    const title = data.title || "";

    return $(`
            <div class="dashboard-box justify-content-around dashboard-stats-container">
                <div class="title">
                    ${title}
                </div>
                ${createTooltip(data.tooltip)}
                <table class="table retards-table" id="${Math.floor(Math.random() * Math.floor(10000))}">
                </table>
            </div>
    `);
}

function calculateChartsFontSize() {
    let width = document.body.clientWidth;
    return Math.floor(width / 120);
}

/**
 * @param {*} data
 * @param {{route: string|null, variable: string|null}} pagination
 * @return {boolean|jQuery}
 */
function createSimpleChart(data, {route, variable, cssClass} = {route: null, variable: null, cssClass: null}) {
    if(!data) {
        console.error(`Invalid data for "${data.title}"`);
        return false;
    }

    const title = data.title || "";

    let pagination = ``;
    if(route !== null && variable !== null) {
        pagination = `
            <div class="range-buttons ${mode === MODE_EDIT ? 'd-none' : ''}">
                <div class="arrow-chart"
                     onclick="drawChartWithHisto($(this), '${route}', 'before', ${variable})">
                    <i class="fas fa-chevron-left pointer"></i>
                </div>
                <span class="firstDay" data-day="TO DO"></span> -
                <span class="lastDay" data-day="TO DO"></span>
                <div class="arrow-chart"
                     onclick="drawChartWithHisto($(this), '${route}', 'after', ${variable})">
                    <i class="fas fa-chevron-right pointer"></i>
                </div>
            </div>
        `;
    }
    return $(`
        <div class="dashboard-box justify-content-around dashboard-stats-container">
            <div class="title">
                ${title}
            </div>
            ${createTooltip(data.tooltip)}
            <div>
                <canvas class="${cssClass || ''}"></canvas>
            </div>
            ${pagination}
        </div>
    `);
}

/**
 * @param {*} data
 * @return {boolean|jQuery}
 */
function createCarrierTrackingElement(data) {
    if(!data || data.carriers === undefined) {
        console.error(`Invalid data for carrier tracking element.`);
        return false;
    }

    const carriers = Array.isArray(data.carriers) ? data.carriers.join() : data.carriers;
    const tooltip = data.tooltip || "";
    const title = data.title || "";

    return $(`
            <div class="dashboard-box justify-content-around dashboard-stats-container">
                <div class="title">
                    ${title}
                </div>
                ${createTooltip(data.tooltip)}
                <p>${carriers}</p>
            </div>
    `);
}

/**
 * @param {*} data
 * @return {boolean|jQuery}
 */
function createOngoingPackElement(data) {
    if(!data || data.count === undefined) {
        console.error(`Invalid data for ongoing pack element.`);
        return false;
    }

    return $('<div/>', {
        class: 'dashboard-box text-center justify-content-around dashboard-stats-container',
        html: [
            createTooltip(data.tooltip),
            data.title
                ? $('<div/>', {
                    class: 'text-center title ellipsis',
                    text: data.title
                })
                : undefined,
            data.subtitle
                ? $('<div/>', {
                    class: 'location-label ellipsis small',
                    text: data.subtitle
                })
                : undefined,
            data.count !== undefined
                ? $('<div/>', {
                    class: 'align-items-center',
                    html: `<div class="dashboard-stats dashboard-stats-counter">${data.count ? data.count : '-'}</div>`
                })
                : undefined,
            data.delay
                ? $('<div/>', {
                    class: `text-center title dashboard-stats-delay-title ${data.delay < 0 ? 'red' : ''}`,
                    text: data.delay < 0
                        ? 'Retard : '
                        : 'A traiter sous :'
                })
                : undefined,
            data.delay
                ? $('<div/>', {
                    class: `dashboard-stats dashboard-stats-delay ${data.delay < 0 ? 'red' : ''}`,
                    text: renderMillisecondsToDelay(Math.abs(data.delay), 'display')
                })
                : undefined,

        ].filter(Boolean)
    });
}


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//ne supprimez pas et mettez pas les fonction de creation des composants en dessous


//fonctions à sortir dans un autre fichier

function drawChartWithHisto($button, path, beforeAfter = 'now', chart = null, preferCacheData = false) {
    return new Promise(function(resolve) {
        if($button.length == 0) {
            resolve();
        } else {
            let $dashboardBox = $button.closest('.dashboard-box');
            let $rangeBtns = $dashboardBox.find('.range-buttons');
            let $firstDay = $rangeBtns.find('.firstDay');
            let $lastDay = $rangeBtns.find('.lastDay');
            let $canvas = $dashboardBox.find('canvas');
            if(!preferCacheData) {
                let params = {
                    'firstDay': $firstDay.data('day'),
                    'lastDay': $lastDay.data('day'),
                    'beforeAfter': beforeAfter
                };
                $.get(Routing.generate(path), params, function(data) {
                    $firstDay.text(data.firstDay);
                    $firstDay.data('day', data.firstDayData);
                    $lastDay.text(data.lastDay);
                    $lastDay.data('day', data.lastDayData);
                    $rangeBtns.removeClass('d-none');

                    const chartData = Object.keys(data.data).reduce((previous, currentKeys) => {
                        previous[currentKeys] = (data.data[currentKeys].count || data.data[currentKeys] || 0);
                        return previous;
                    }, {});

                    dashboardChartsData[$canvas.attr('id')] = chartData;
                    chart = createAndUpdateSimpleChart($canvas, chart, chartData);
                    resolve(chart);
                });
            } else {
                const chartData = dashboardChartsData[$canvas.attr('id')];

                chart = createAndUpdateSimpleChart($canvas, chart, chartData, true);
                resolve(chart);
            }
        }
    });
}

function updateSimpleChartData(chart, data, label, stack = false,
                               {data: subData, label: lineChartLabel} = {data: undefined, label: undefined}) {
    chart.data.datasets = [{data: [], label}];
    chart.data.labels = [];
    const dataKeys = Object.keys(data).filter((key) => key !== 'stack');
    for(const key of dataKeys) {
        chart.data.labels.push(key);
        chart.data.datasets[0].data.push(data[key]);
    }

    const dataLength = chart.data.datasets[0].data.length;
    if(dataLength > 0) {
        chart.data.datasets[0].backgroundColor = new Array(dataLength);
        chart.data.datasets[0].backgroundColor.fill('#A3D1FF');
    }


    if(subData) {
        const subColor = '#999';
        chart.data.datasets.push({
            label: lineChartLabel,
            backgroundColor: (new Array(dataLength)).fill(subColor),
            data: Object.values(subData)
        });

        chart.legend.display = true;
    }
    if(stack) {
        chart.options.scales.yAxes[0].stacked = true;
        chart.options.scales.xAxes[0].stacked = true;
        data.stack.forEach((stack) => {
            chart.data.datasets.push(stack);
        });
    }

    chart.update();
}

function createAndUpdateSimpleChart($canvas, chart, data, forceCreation = false, disableAnimation = false) {
    if(forceCreation || !chart) {
        chart = newChart($canvas, false, disableAnimation);
    }
    if(data) {
        updateSimpleChartData(
            chart,
            data.chartData || data,
            data.label || '',
            data.stack || false,
            {
                data: data.subCounters,
                label: data.subLabel
            }
        );
    }

    return chart;
}

function newChart($canvasId, redForLastData = false, disableAnimation = false) {
    if($canvasId.length) {
        const fontSize = currentChartsFontSize;
        const fontStyle = undefined;

        const chart = new Chart($canvasId, {
            type: 'bar',
            data: {},
            options: {
                layout: {
                    padding: {
                        top: 30
                    }
                },
                tooltips: false,
                responsive: true,
                legend: {
                    position: 'bottom',
                    labels: {
                        fontSize,
                        fontStyle,
                        filter: function(item) {
                            return Boolean(item && item.text);
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontSizeYAxes,
                            fontStyle,
                            beginAtZero: true,
                            callback: (value) => {
                                if(Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        }
                    }],
                    xAxes: [{
                        ticks: {
                            fontSize,
                            fontStyle
                        }
                    }]
                },
                hover: {mode: null},
                animation: {
                    duration: disableAnimation ? 0 : 1000,
                    onComplete() {
                        buildLabelOnBarChart(this, redForLastData);
                    }
                }
            }
        });
        return chart;
    } else {
        return null;
    }
}

function buildLabelOnBarChart(chartInstance, redForFirstData) {
    let ctx = (chartInstance.chart.ctx);
    ctx.font = Chart.helpers.fontString(Chart.defaults.global.defaultFontFamily, 'bold', Chart.defaults.global.defaultFontFamily);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.strokeStyle = 'black';
    ctx.shadowColor = '#999';


    const fontSize = currentChartsFontSize;

    const figurePaddingHorizontal = 8;
    const figurePaddingVertical = 4;
    const figureColor = '#666666';
    const rectColor = '#FFFFFF';

    const yAdjust = 23;
    let stackedQuantities = [];
    chartInstance.data.datasets.forEach(function(dataset, index) {
        if(chartInstance.isDatasetVisible(index)) {
            let containsNegativValues = dataset.data.some((current) => (current < 0));
            for(let i = 0; i < dataset.data.length; i++) {
                for(let key in dataset._meta) {
                    const value = parseInt(dataset.data[i]);
                    const isNegativ = (value < 0);
                    if(value !== 0) {
                        let {x, y, base} = dataset._meta[key].data[i]._model;
                        const figure = dataset.data[i];
                        const rectHeight = fontSize + (figurePaddingVertical * 2);
                        y = isNegativ
                            ? (base - rectHeight)
                            : (containsNegativValues
                                ? (base + (rectHeight / 2))
                                : (y - yAdjust));


                        if(stackedQuantities[x]) {
                            if(stackedQuantities[x].y > y) {
                                stackedQuantities[x].y = y;
                            }
                            stackedQuantities[x].figure += figure;
                        } else {
                            stackedQuantities[x] = {
                                y,
                                figure
                            }
                        }
                    }
                }
            }
        }
    });
    Object.keys(stackedQuantities).forEach((x) => {
        const y = stackedQuantities[x].y;
        const figure = stackedQuantities[x].figure;
        const {width} = ctx.measureText(figure);
        const rectY = y - figurePaddingVertical;
        const rectX = x - (width / 2) - figurePaddingHorizontal;
        const rectWidth = width + (figurePaddingHorizontal * 2);
        const rectHeight = fontSize + (figurePaddingVertical * 2);

        // context only for rect
        ctx.shadowBlur = 2;
        ctx.shadowOffsetX = 1;
        ctx.shadowOffsetY = 1;
        ctx.fillStyle = rectColor;
        ctx.fillRect(rectX, rectY, rectWidth, rectHeight);

        // context only for text
        ctx.shadowBlur = 0;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;
        ctx.fillStyle = figureColor;
        ctx.fillText(figure, x, y);
    })
}

function loadLatePacks($table, data) {
    let datatableColisConfig = {
        responsive: true,
        domConfig: {
            needsMinimalDomOverride: true
        },
        paging: false,
        scrollCollapse: true,
        scrollY: '22vh',
        processing: true,
        order: [[2, 'desc']],
        columns: [
            {"data": 'pack', 'name': 'pack', 'title': 'Colis'},
            {"data": 'date', 'name': 'date', 'title': 'Dépose'},
            {
                "data": 'delay',
                'name': 'delay',
                'title': 'Délai',
                render: (milliseconds, type) => renderMillisecondsToDelay(milliseconds, type)
            },
            {"data": 'location', 'name': 'location', 'title': 'Emplacement'},
        ]
    };
    if(mode === MODE_EDIT) {
        datatableColisConfig.data = data.tableData;
    } else {
        datatableColisConfig.ajax = {
            "url": Routing.generate('api_retard', true),
            "type": "GET",
        };
    }

    initDataTable($table.attr('id'), datatableColisConfig);
}

function createAndUpdateMultipleCharts($canvas,
                                       chart,
                                       data,
                                       forceCreation = false,
                                       redForLastData = true,
                                       disableAnimation = false) {
    if(forceCreation || !chart) {
        chart = newChart($canvas, redForLastData, disableAnimation);
    }
    if(data) {
        updateMultipleChartData(chart, data);
    }
    return chart;
}

/**
 * @param chart
 * @param data
 */
function updateMultipleChartData(chart, data) {
    const chartColors = data.chartColors || [];
    const chartData = data.chartData || [];
    chart.data.labels = [];
    chart.data.datasets = [];

    const dataKeys = Object.keys(chartData);
    for(const key of dataKeys) {
        const dataSubKeys = Object.keys(chartData[key]);
        chart.data.labels.push(key);
        for(const subKey of dataSubKeys) {
            let dataset = chart.data.datasets.find(({label}) => (label === subKey));
            if(!dataset) {
                dataset = {
                    label: subKey,
                    backgroundColor: (chartColors
                            ? (
                                (chartColors && chartColors[subKey])
                                || (`#${((1 << 24) * Math.random() | 0).toString(16)}`)
                            )
                            : '#a3d1ff'
                    ),
                    data: []
                };
                chart.data.datasets.push(dataset);
            }
            dataset.data.push(chartData[key][subKey]);
        }
    }
    chart.update();
}
