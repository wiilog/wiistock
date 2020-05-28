let datatableColis;

let chartArrivalUm;
let chartAssoRecep;
let chartDailyArrival;
let chartWeeklyArrival;
let chartColis;
let chartMonetaryFiability;
let chartFirstForAdmin;
let chartSecondForAdmin;
let chartTreatedPacks;

let currentChartsFontSize;
const dashboardChartsData = {};

const DASHBOARD_ARRIVAL_NAME = 'arrivage';
const DASHBOARD_DOCK_NAME = 'quai';
const DASHBOARD_ADMIN_NAME = 'admin';
const DASHBOARD_PACKAGING_NAME = 'emballage';
let displayedDashboards = [];
const PAGE_CONFIGS = {
    [DASHBOARD_ARRIVAL_NAME]: {
        loadData: loadArrivalDashboard,
        isAlreadyLoaded: false
    },
    [DASHBOARD_DOCK_NAME]: {
        loadData: loadDockDashboard,
        isAlreadyLoaded: false
    },
    [DASHBOARD_ADMIN_NAME]: {
        loadData: loadAdminDashboard,
        isAlreadyLoaded: false
    },
    [DASHBOARD_PACKAGING_NAME]: {
        loadData: loadPackagingData,
        isAlreadyLoaded: false
    }
}

$(function () {
    // config chart js
    Chart.defaults.global.defaultFontFamily = 'Myriad';
    Chart.defaults.global.responsive = true;
    Chart.defaults.global.maintainAspectRatio = false;
    currentChartsFontSize = calculateChartsFontSize();
    $('.indicator').each(function() {
        displayedDashboards.push($(this).data('name'));
    });
    if (!isDashboardExt()) {
        const knownHash = ([DASHBOARD_ARRIVAL_NAME, DASHBOARD_DOCK_NAME, DASHBOARD_ADMIN_NAME, DASHBOARD_PACKAGING_NAME].indexOf(getUrlHash()) > -1);
        if (!knownHash) {
            window.location.hash = displayedDashboards.length > 0 ? displayedDashboards[0] : '';
        }
        setActiveDashboard(getUrlHash());
    }
    updateRefreshDate();
    loadProperData();

    initTooltips($('.has-tooltip'));

    if (!isDashboardExt()) {
        let reloadFrequency = 1000 * 60 * 5; // 5min
        setInterval(reloadData, reloadFrequency);
    }

    let $indicators = $('#indicators');
    $('#btnIndicators').mouseenter(function () {
        $indicators.fadeIn();
    });
    $('#blocIndicators').mouseleave(function () {
        $indicators.fadeOut();
    });

    $(document).on('keydown', function (e) {
        if (!$('.carousel-indicators').hasClass('d-none')) {
            let activeBtn = $('#carousel-dashboard').find('[data-slide-to].active');
            if (e.which === 37) {
                activeBtn.prev('li').click()
            } else if (e.which === 39) {
                activeBtn.next('li').click()
            }
        }
    });

    $(window).resize(function () {
        let newFontSize = calculateChartsFontSize();
        if (newFontSize !== currentChartsFontSize) {
            currentChartsFontSize = newFontSize;
            loadProperData(true);
        }
    });

    refreshPageTitle();
    const $carouselDashboard = $('#carousel-dashboard');
    // slide during
    $carouselDashboard.on('slide.bs.carousel', (event) => {
        showDashboardSpinner();
    });

    // slide end
    $carouselDashboard.on('slid.bs.carousel', () => {
        window.location.hash = $('#carousel-dashboard .carousel-item.active').data('name');
        loadProperData(true);
        refreshPageTitle();
    });
});

function showDashboardSpinner() {
    let $spinner = $('.dashboard-spinner');
    $spinner.removeClass('d-none');
}

function hideDashboardSpinner(activeDashboardName) {
    if (!activeDashboardName
        || activeDashboardName === getActiveDashboardName()) {
        let $spinner = $('.dashboard-spinner');
        $spinner.addClass('d-none');
    }
}

function loadProperData(preferCache = false) {
    const activeDashboardName = getActiveDashboardName();
    if (PAGE_CONFIGS[activeDashboardName]
        && (preferCache || !PAGE_CONFIGS[activeDashboardName].isAlreadyLoaded)) {
        PAGE_CONFIGS[activeDashboardName]
            .loadData(PAGE_CONFIGS[activeDashboardName].isAlreadyLoaded && preferCache)
            .then(() => {
                hideDashboardSpinner(activeDashboardName);
            });
        PAGE_CONFIGS[activeDashboardName].isAlreadyLoaded = true;
    } else {
        hideDashboardSpinner(activeDashboardName);
        $('.header-title span').text('');
    }
}

function resizeDatatable() {
    if (datatableColis) {
        datatableColis.columns.adjust().draw();
    }
}

function loadPackagingData(preferCache) {
    return new Promise(function (resolve) {
        if (preferCache) {
            const $canvas = $('#chartTreatedPacks');
            const chartData = dashboardChartsData[$canvas.attr('id')];
            createAndUpdateMultipleCharts($canvas, chartTreatedPacks, chartData, false, false);
            resolve();
        } else {
            if (isDashboardExt()) {
                const data = $('#dashboard-data').data('data');
                treatPackagingData(data);
                resolve();
            }
            else {
                // if we are not on dashboardExt we load data via ajax
                let pathForPackagingData = Routing.generate('get_indicators_monitoring_packaging', true);
                $.get(pathForPackagingData, function (data) {
                    treatPackagingData(data);
                    resolve();
                });
            }
        }
    });
}

function treatPackagingData({counters, chartData, chartColors}) {
    const countersKeys = Object.keys(counters || {});
    let total = 0;
    for(const key of countersKeys) {
        total += fillPackagingCard(key, counters[key]) || 0;
    }

    $('#packagingTotal').find('.dashboard-stats-counter').html(total || '-');
    const $canvas = $('#chartTreatedPacks');
    dashboardChartsData[$canvas.attr('id')] = {
        data: chartData,
        chartColors
    };
    chartTreatedPacks = createAndUpdateMultipleCharts($canvas, chartTreatedPacks, dashboardChartsData[$canvas.attr('id')], true, false);
}

function fillPackagingCard(cardId, data) {
    let $container = $('#' + cardId);
    $container.find('.location-label').html(data ? data.label : '-');
    $container.find('.dashboard-stats-counter').html(data && data.count ? data.count : '-');
    let $titleDelayContainer = $container.find('.dashboard-stats-delay-title');
    let $titleDelayValue = $container.find('.dashboard-stats-delay');
    if (data && data.delay < 0) {
        $titleDelayContainer.html('Retard : ');
        $titleDelayContainer.addClass('red');
        $titleDelayValue.html(renderMillisecondsToDelayDatatable(Math.abs(data.delay), 'display'));
        $titleDelayValue.addClass('red');
    } else if (data && data.delay > 0) {
        $titleDelayContainer.html('A traiter sous : ');
        $titleDelayContainer.removeClass('red');
        $titleDelayValue.html(renderMillisecondsToDelayDatatable(data.delay, 'display'));
        $titleDelayValue.removeClass('red');
    } else {
        $titleDelayValue.html('-');
    }
    return data && $container.hasClass('contribute-to-total') ? data.count : 0;
}

function loadArrivalDashboard(preferCache) {
    return Promise
        .all([
            drawChartWithHisto($('#chartArrivalUm'), 'get_arrival_um_statistics', 'now', chartArrivalUm, preferCache),
            drawChartWithHisto($('#chartAssocRecep'), 'get_asso_recep_statistics', 'now', chartAssoRecep, preferCache),
            drawSimpleChart($('#chartMonetaryFiability'), 'get_monetary_fiability_statistics', chartMonetaryFiability, preferCache),
            ...(!preferCache ? [loadRetards()] : [])
        ])
        .then(([chartArrivalUmLocal, chartAssoRecepLocal, chartMonetaryFiabilityLocal]) => {
            resizeDatatable();
            chartArrivalUm = chartArrivalUmLocal;
            chartAssoRecep = chartAssoRecepLocal;
            chartMonetaryFiability = chartMonetaryFiabilityLocal;
        });
}

function loadDockDashboard(preferCache) {
    return new Promise((resolve => {
        refreshIndicatorsReceptionDock().then(() => {
            Promise
                .all([
                    drawSimpleChart($('#chartDailyArrival'), 'get_daily_arrivals_statistics', chartDailyArrival, preferCache),
                    drawSimpleChart($('#chartWeeklyArrival'), 'get_weekly_arrivals_statistics', chartWeeklyArrival, preferCache),
                    drawSimpleChart($('#chartColis'), 'get_daily_packs_statistics', chartColis, preferCache),
                    ...(
                        !preferCache
                            ? [
                                updateCarriers()
                            ]
                            : []
                    )
                ])
                .then(([chartDailyArrivalLocal, chartWeeklyArrivalLocal, chartColisLocal]) => {
                    chartDailyArrival = chartDailyArrivalLocal;
                    chartWeeklyArrival = chartWeeklyArrivalLocal;
                    chartColis = chartColisLocal;
                    resolve();
                });
        });
    }));
}

function loadAdminDashboard(preferCache) {
    return new Promise((resolve => {
        refreshIndicatorsReceptionAdmin().then(() => {
            Promise
                .all([
                    drawMultipleBarChart($('#chartFirstForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 1}, 1, chartFirstForAdmin, preferCache),
                    drawMultipleBarChart($('#chartSecondForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 2}, 2, chartSecondForAdmin, preferCache),
                ])
                .then(([chartFirstForAdminLocal, chartSecondForAdminLocal]) => {
                    chartFirstForAdmin = chartFirstForAdminLocal;
                    chartSecondForAdmin = chartSecondForAdminLocal;
                    resolve();
                });
        });
    }));
}


function reloadData() {
    Object.keys(PAGE_CONFIGS).forEach((dashboardName) => {
        PAGE_CONFIGS[dashboardName].isAlreadyLoaded = false;
    });

    loadProperData();
    updateRefreshDate();
}

function updateRefreshDate() {
    const treatDate = (date) => {
        const $refreshDate = $('.refreshDate');
        $refreshDate.text(date);
        $refreshDate.parent().removeClass('d-none');
    };
    if (isDashboardExt()) {
        const date = $('#dashboard-refresh-date').data('date')
        treatDate(date);
    }
    else {
        $.get(Routing.generate('last_refresh'), function (response) {
            if (response && response.success) {
                treatDate(response.date);
            }
        });
    }
}

function updateSimpleChartData(
    chart,
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

/**
 * @param chart
 * @param chartData
 * @param chartColors boolean or Object.<Nature, Color>
 */
function updateMultipleChartData(chart, chartData, chartColors) {
    chart.data.labels = [];
    chart.data.datasets = [];

    const dataKeys = Object.keys(chartData);
    for (const key of dataKeys) {
        const dataSubKeys = Object.keys(chartData[key]);
        chart.data.labels.push(key);
        for (const subKey of dataSubKeys) {
            let dataset = chart.data.datasets.find(({label}) => (label === subKey));
            if (!dataset) {
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

function drawSimpleChart($canvas, path, chart, preferCacheData = false) {
    return new Promise(function (resolve) {
        if ($canvas.length == 0) {
            resolve();
        } else {
            if (!preferCacheData) {
                $.get(Routing.generate(path), function (data) {
                    dashboardChartsData[$canvas.attr('id')] = data;
                    chart = createAndUpdateSimpleChart($canvas, chart, data);
                    resolve(chart);
                });
            } else {
                const data = dashboardChartsData[$canvas.attr('id')];
                chart = createAndUpdateSimpleChart($canvas, chart, data, true);
                resolve(chart);
            }
        }
    });
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

function createAndUpdateMultipleCharts($canvas, chart, data, forceCreation = false, redForLastData = true) {
    if (forceCreation || !chart) {
        chart = newChart($canvas, redForLastData);
    }
    if (data) {
        updateMultipleChartData(chart, data.data, (data.chartColors || {}));
    }
    return chart;
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

function drawMultipleBarChart($canvas, path, params, chartNumber, chart, preferCacheData = false) {
    return new Promise(function (resolve) {
        if ($canvas.length == 0) {
            resolve();
        } else {
            if (!preferCacheData) {
                $.get(Routing.generate(path, params), function (data) {
                    $('#empForChart' + chartNumber).text(data.location);
                    $('#totalForChart' + chartNumber).text(data.total);
                    dashboardChartsData[$canvas.attr('id')] = data;

                    chart = createAndUpdateMultipleCharts($canvas, chart, data);
                    resolve(chart);
                });
            } else {
                data = dashboardChartsData[$canvas.attr('id')];
                chart = createAndUpdateMultipleCharts($canvas, chart, data, true);
                resolve(chart);
            }
        }
    });
}

function goToFilteredDemande(type, filter) {
    let path = '';
    if (type === 'livraison') {
        path = 'demande_index';
    } else if (type === 'collecte') {
        path = 'collecte_index';
    } else if (type === 'manutention') {
        path = 'manutention_index';
    }

    let params = {
        reception: 0,
        filter: filter
    };
    let route = Routing.generate(path, params);
    window.location.href = route;
}

function newChart($canvasId, redForLastData = false) {
    if ($canvasId.length) {
        const fontSize = currentChartsFontSize;
        const fontStyle = isDashboardExt()
            ? 'bold'
            : undefined;

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
                            fontSize,
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

function loadRetards() {
    return new Promise(function (resolve) {
        const $retardsTable = $('.retards-table');
        if (datatableColis) {
            datatableColis.ajax.reload(() => {
                resolve();
            });
        } else {
            let datatableColisConfig = {
                responsive: true,
                domConfig: {
                    needsMinimalDomOverride: true
                },
                paging: false,
                scrollCollapse: true,
                scrollY: '22vh',
                processing: true,
                ajax: {
                    "url": Routing.generate('api_retard', true),
                    "type": "GET",
                },
                initCompleteCallback: () => {
                    resolve();
                },
                order: [[2, 'desc']],
                columns: [
                    {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
                    {"data": 'date', 'name': 'date', 'title': 'Dépose'},
                    {
                        "data": 'delay',
                        'name': 'delay',
                        'title': 'Délai',
                        render: (milliseconds, type) => renderMillisecondsToDelayDatatable(milliseconds, type)
                    },
                    {"data": 'emp', 'name': 'emp', 'title': 'Emplacement'},
                ]
            };
            datatableColis = initDataTable($retardsTable.attr('id'), datatableColisConfig);
        }
    });
}

function refreshIndicatorsReceptionDock() {
    return new Promise(function (resolve) {
        $.get(Routing.generate('get_indicators_reception_dock'), function (data) {
            refreshCounter($('#remaining-urgences-box-dock'), data.urgenceCount);
            refreshCounter($('#remaining-daily-urgences-box-dock'), data.dailyUrgenceCount);
            refreshCounter($('#encours-dock-box'), data.enCoursDock);
            refreshCounter($('#encours-clearance-box-dock'), data.enCoursClearance);
            refreshCounter($('#encours-cleared-box'), data.enCoursCleared);
            refreshCounter($('#encours-dropzone-box'), data.enCoursDropzone);
            resolve();
        });
    });
}

function refreshIndicatorsReceptionAdmin() {
    return new Promise(function (resolve) {
        $.get(Routing.generate('get_indicators_reception_admin', true), function (data) {
            refreshCounter($('#encours-clearance-box-admin'), data.enCoursClearance);
            refreshCounter($('#encours-litige-box'), data.enCoursLitige);
            refreshCounter($('#encours-urgence-box'), data.enCoursUrgence, true);
            refreshCounter($('#remaining-urgences-box-admin'), data.urgenceCount);
            resolve();
        });
    });
}

function refreshCounter($counterCountainer, data, needsRedColorIfPositiv = false) {
    let counter;

    if (typeof data === 'object') {
        const label = data ? data.label : '-';
        counter = data ? data.count : '-';
        $counterCountainer.find('.location-label').text('(' + label + ')');
    } else {
        counter = data;
    }
    if (counter > 0 && needsRedColorIfPositiv) {
        $counterCountainer.find('.dashboard-stats').addClass('red');
        $counterCountainer.find('.fas').addClass('red fa-exclamation-triangle');
    } else {
        $counterCountainer.find('.dashboard-stats').removeClass('red');
        $counterCountainer.find('.fas').removeClass('red fa-exclamation-triangle');
    }
    $counterCountainer.find('.dashboard-stats').text(counter);
}

function updateCarriers() {
    return new Promise(function (resolve) {
        $.get(Routing.generate('get_daily_carriers_statistics'), function (data) {
            const $container = $('#statistics-arrival-carriers');
            $container.empty();
            const cssClass = `${isDashboardExt() ? 'medium-font' : ''} m-0`;
            $container.append(
                ...((data || []).map((carrier) => ($('<li/>', {text: carrier, class: cssClass}))))
            );
            resolve();
        });
    });
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

function isDashboardExt() {
    const $isDashboardExt = $('#isDashboardExt');
    return ($isDashboardExt.length > 0 ? ($isDashboardExt.val() === "1") : false);
}

function refreshPageTitle() {
    const $carouselDashboard = $('#carousel-dashboard');
    const $activeCarousel = $carouselDashboard.find('.carousel-item.active').first();
    const $pageTitle = $activeCarousel.length > 0
        ? $activeCarousel.find('input.page-title')
        : $('input.page-title');
    const pageTitle = $pageTitle.val();

    if (pageTitle) {
        document.title = `FollowGT${(pageTitle ? ' | ' : '') + pageTitle}`;

        const words = pageTitle.split('|');

        if (words && words.length > 0) {
            const $titleContainer = $('<span/>');
            for (let wordIndex = 0; wordIndex < words.length; wordIndex++) {
                if ($titleContainer.children().length > 0) {
                    $titleContainer.append(' | ')
                }
                const className = (wordIndex === (words.length - 1)) ? 'bold' : undefined;
                $titleContainer.append($('<span/>', {class: className, text: words[wordIndex]}));
            }
            $('.main-header .header-title').html($titleContainer);
        }
    }
}

function calculateChartsFontSize() {
    let width = document.body.clientWidth;
    return Math.floor(width / 120);
}

function setActiveDashboard(hash) {
    if (!displayedDashboards.includes(hash)) {
        hash = displayedDashboards.length > 0 ? displayedDashboards[0] : '';
        window.location.hash = hash;
    }
    const $activeIndic = $(`#carousel-dashboard .carousel-indicators > li[data-name="${hash}"]`);
    $activeIndic.addClass('active');
    $activeIndic.click();
    $(`#carousel-dashboard .carousel-item[data-name="${hash}"]`).addClass('active');
}

function getActiveDashboardName(activeDashboard = $('.carousel-item.active')) {
    return isDashboardExt()
        ? $('#dashboard-name').val()
        : activeDashboard.data('name');
}

function getUrlHash() {
    const hash = (window.location.hash || '');
    return hash.charAt(0) === '#'
        ? hash.slice(1)
        : hash;
}
