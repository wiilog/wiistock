let currentChartsFontSize;
let fontSizeYAxes;
const METER_KEY_ONGOING_PACK = 'ongoing_packs';
const CARRIER_INDICATOR = 'carrier_indicator';
const DAILY_ARRIVALS = 'daily_arrivals';

$(function() {
    Chart.defaults.global.defaultFontFamily = 'Myriad';
    Chart.defaults.global.responsive = true;
    Chart.defaults.global.maintainAspectRatio = false;
    currentChartsFontSize = calculateChartsFontSize();
    fontSizeYAxes = currentChartsFontSize *0.5;
});

const creators = {
    [METER_KEY_ONGOING_PACK]: createOngoingPackElement,
    [CARRIER_INDICATOR]: createCarrierIndicatorElement,
    [DAILY_ARRIVALS]: createDailyArrivalsGraph
};


/**
 *
 * @param {jQuery} $container
 * @param {string} meterKey
 * @param {boolean=false} isExample
 * @param {*} data
 * @return {boolean}
 */
function renderComponent(meterKey,
                         $container,
                         data,
                         isExample = false) {
    $container.html('');
    if (!creators[meterKey]) {
        console.error(`No function for create element for ${meterKey} key.`);
        return false;
    }
    else {
        const $element = creators[meterKey](data, isExample);
        if ($element) {
            $container.html($element);
            if ($element.find('canvas').length > 0) {
                createAndUpdateSimpleChart($element.find('canvas'), null, data.graphData);
            }
        }
        return !!$element;
    }
}

function calculateChartsFontSize() {
    let width = document.body.clientWidth;
    return Math.floor(width / 120);
}

/**
 * @param {*} data
 * @param {boolean=false} isExample
 * @return {boolean|jQuery}
 */
function createDailyArrivalsGraph(data, isExample = false) {
    if (!data) {
        console.error(`Invalid data for daily arrivals graphs element.`);
        return false;
    }
    let tooltip = data.tooltip || "";
    let title = data.title || "";
    return $('<div/>', {
        class: `dashboard-box-container ${isExample ? 'flex-fill' : ''}`,
        html: $('<div/>', {
            class: 'dashboard-box justify-content-around dashboard-stats-container',
            html: `<div class="title">
                        ${title}
                    </div>
                    <div class="points has-tooltip"
                        title="${tooltip}">
                            <i class="fa fa-question ml-1"></i>
                    </div>
                    <div class="h-100">
                        <canvas width="300" height="90"></canvas>
                    </div>
                    <div class="range-buttons ${isExample ? 'd-none' : ''}">
                        <div class="arrow-chart"
                             onclick="drawChartWithHisto($(this), 'get_arrival_um_statistics', 'before', chartArrivalUm)">
                            <i class="fas fa-chevron-left pointer"></i>
                        </div>
                        <span class="firstDay" data-day="{{ firstDayOfWeek }}"></span> -
                        <span class="lastDay" data-day="{{ lastDayOfWeek }}"></span>
                        <div class="arrow-chart"
                             onclick="drawChartWithHisto($(this), 'get_arrival_um_statistics', 'after', chartArrivalUm)">
                            <i class="fas fa-chevron-right pointer"></i>
                        </div>
                    </div>`
        })
    });
}

/**
 * @param {*} data
 * @param {boolean=false} isExample
 * @return {boolean|jQuery}
 */
function createCarrierIndicatorElement(data, isExample = false) {
    if (!data || data.carriers === undefined) {
        console.error(`Invalid data for carrier indicator element.`);
        return false;
    }

    let carriers = Array.isArray(data.carriers) ? data.carriers.join() : data.carriers;
    let tooltip = data.tooltip || "";
    let title = data.title || "";

    return $('<div/>', {
        class: `dashboard-box-container ${isExample ? 'flex-fill' : ''}`,
        html: $('<div/>', {
            class: `dashboard-box justify-content-around dashboard-stats-container`,
            html: `
                <div class="title">
                    ${title}
                </div>
                <div class="points has-tooltip" title="${tooltip}">
                    <i class="fa fa-question ml-1"></i>
                </div>
                <p>${carriers}</p>
            `,
        })
    });
}

/**
 * @param {*} data
 * @param {boolean=false} isExample
 * @return {boolean|jQuery}
 */
function createOngoingPackElement(data,
                                  isExample) {
    if (!data || data.count === undefined) {
        console.error(`Invalid data for ongoing pack element.`);
        return false;
    }

    return $('<div/>', {
        class: `dashboard-box-container ${isExample ? 'flex-fill' : ''}`,
        html: $('<div/>', {
            class: 'dashboard-box text-center justify-content-around dashboard-stats-container',
            html: [
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
        })
    });
}

function drawChartWithHisto($button, path, beforeAfter = 'now', chart = null, preferCacheData = false) {
    return new Promise(function (resolve) {
        if ($button.length == 0) {
            resolve();
        } else {
            let $dashboardBox = $button.closest('.dashboard-box');
            let $rangeBtns = $dashboardBox.find('.range-buttons');
            let $firstDay = $rangeBtns.find('.firstDay');
            let $lastDay = $rangeBtns.find('.lastDay');
            let $canvas = $dashboardBox.find('canvas');
            if (!preferCacheData) {
                let params = {
                    'firstDay': $firstDay.data('day'),
                    'lastDay': $lastDay.data('day'),
                    'beforeAfter': beforeAfter
                };
                $.get(Routing.generate(path), params, function (data) {
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

function updateSimpleChartData(chart,
                               data,
                               label,
                               {data: subData, label: lineChartLabel} = {data: undefined, label: undefined}) {
    chart.data.datasets = [{data: [], label}];
    chart.data.labels = [];
    const dataKeys = Object.keys(data);
    for (const key of dataKeys) {
        chart.data.labels.push(key);
        chart.data.datasets[0].data.push(data[key]);
    }

    const dataLength = chart.data.datasets[0].data.length;
    if (dataLength > 0) {
        chart.data.datasets[0].backgroundColor = new Array(dataLength);
        chart.data.datasets[0].backgroundColor.fill('#A3D1FF');
    }

    if (subData) {
        const subColor = '#999';
        chart.data.datasets.push({
            label: lineChartLabel,
            backgroundColor: (new Array(dataLength)).fill(subColor),
            data: Object.values(subData)
        });

        chart.legend.display = true;
    }

    chart.update();
}

function createAndUpdateSimpleChart($canvas, chart, data, forceCreation = false) {
    if (forceCreation || !chart) {
        chart = newChart($canvas, false);
    }

    if (data) {
        updateSimpleChartData(
            chart,
            data.data || data,
            data.data && data.label,
            {
                data: data.subCounters,
                label: data.subLabel
            }
        );
    }

    return chart;
}

function newChart($canvasId, redForLastData = false) {
    if ($canvasId.length) {
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
                        filter: function (item) {
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
                                if (Math.floor(value) === value) {
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

    chartInstance.data.datasets.forEach(function (dataset, index) {
        if (chartInstance.isDatasetVisible(index)) {
            let containsNegativValues = dataset.data.some((current) => (current < 0));
            for (let i = 0; i < dataset.data.length; i++) {
                for (let key in dataset._meta) {
                    const value = parseInt(dataset.data[i]);
                    const isNegativ = (value < 0);
                    if (value !== 0) {
                        let {x, y, base} = dataset._meta[key].data[i]._model;
                        const figure = dataset.data[i];
                        const {width} = ctx.measureText(figure);
                        const rectWidth = width + (figurePaddingHorizontal * 2);
                        const rectHeight = fontSize + (figurePaddingVertical * 2);

                        y = isNegativ
                            ? (base - rectHeight)
                            : (containsNegativValues
                                ? (base + (rectHeight / 2))
                                : (y - yAdjust));

                        const rectX = x - (width / 2) - figurePaddingHorizontal;
                        const rectY = y - figurePaddingVertical;

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
                        const applyRedFont = (redForFirstData && (i === 0));
                        ctx.fillStyle = applyRedFont ? 'red' : figureColor;
                        ctx.fillText(figure, x, y);
                    }
                }
            }
        }
    });
}
