///
/// Ce fichier étant utilisé sur les dashboards et donc possiblement
/// sur MagicInfo, les opérateurs suivants ne peuvent pas être utilisés
///  - ?? à remplacer par ||
///  - ... dans les objets, à remplacer par un ajout "manuel
///

let currentChartsFontSize;
let fontSizeYAxes;

const MARQUEE_PADDING = `&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`;

const OVERRIDE_FONT_RED = {color: `#CC0000`};

const ONGOING_PACK = 'ongoing_packs';
const DAILY_ARRIVALS = 'daily_arrivals';
const LATE_PACKS = 'late_packs';
const CARRIER_TRACKING = 'carrier_tracking';
const DAILY_ARRIVALS_AND_PACKS = 'daily_arrivals_and_packs';
const RECEIPT_ASSOCIATION = 'receipt_association';
const WEEKLY_ARRIVALS_AND_PACKS = 'weekly_arrivals_and_packs';
const PENDING_REQUESTS = 'pending_requests';
const ENTRIES_TO_HANDLE = 'entries_to_handle';
const PACK_TO_TREAT_FROM = 'pack_to_treat_from';
const DROP_OFF_DISTRIBUTED_PACKS = 'drop_off_distributed_packs';
const ARRIVALS_EMERGENCIES_TO_RECEIVE = 'arrivals_emergencies_to_receive';
const DAILY_ARRIVALS_EMERGENCIES = 'daily_arrivals_emergencies'
const REQUESTS_TO_TREAT = 'requests_to_treat';
const DAILY_HANDLING_INDICATOR = 'daily_handling_indicator';
const ORDERS_TO_TREAT = 'orders_to_treat';
const DAILY_HANDLING = 'daily_handling';
const DAILY_OPERATIONS = 'daily_operations';
const MONETARY_RELIABILITY_GRAPH = 'monetary_reliability_graph';
const MONETARY_RELIABILITY_INDICATOR = 'monetary_reliability_indicator';
const ACTIVE_REFERENCE_ALERTS = 'active_reference_alerts';
const REFERENCE_RELIABILITY = 'reference_reliability';
const DAILY_DISPATCHES = 'daily_dispatches';
const EXTERNAL_IMAGE = 'external_image';
const HANDLING_TRACKING = 'handling_tracking';
let DELAYS = {};

$(function() {
    Chart.defaults.global.defaultFontFamily = 'Myriad';
    Chart.defaults.global.responsive = true;
    Chart.defaults.global.maintainAspectRatio = false;
    currentChartsFontSize = calculateChartsFontSize();
    fontSizeYAxes = currentChartsFontSize * 0.5;
})

$(document).arrive('.scroll, .dashboard-box .title', function() {
    const $element = $(this);
    const $mainParent = $element.closest(`.dashboard-component`);

    if($mainParent.width() < $element.width() || $mainParent.width() < $element[0].scrollWidth) {
        $element.html(`<marquee behavior="alternate" scrollamount=2>${MARQUEE_PADDING}${$element.html()}${MARQUEE_PADDING}</marquee>`);
    }
});

const creators = {
    [ONGOING_PACK]: {
        callback: createIndicatorElement
    },
    [CARRIER_TRACKING]: {
        callback: createCarrierTrackingElement
    },
    [DAILY_ARRIVALS]: {
        callback: createChart,
        arguments: {route: `get_arrival_um_statistics`}
    },
    [LATE_PACKS]: {
        callback: createLatePacksElement
    },
    [DAILY_ARRIVALS_AND_PACKS]: {
        callback: createChart
    },
    [RECEIPT_ASSOCIATION]: {
        callback: createChart,
        arguments: {route: `get_asso_recep_statistics`}
    },
    [WEEKLY_ARRIVALS_AND_PACKS]: {
        callback: createChart
    },
    [PENDING_REQUESTS]: {
        callback: createPendingRequests
    },
    [ENTRIES_TO_HANDLE]: {
        callback: createEntriesToHandleElement
    },
    [PACK_TO_TREAT_FROM]: {
        callback: createChart,
        arguments: {cssClass: 'multiple'}
    },
    [DROP_OFF_DISTRIBUTED_PACKS]: {
        callback: createChart
    },
    [DAILY_DISPATCHES]: {
        callback: createChart
    },
    [ARRIVALS_EMERGENCIES_TO_RECEIVE]: {
        callback: createIndicatorElement
    },
    [DAILY_ARRIVALS_EMERGENCIES]: {
        callback: createIndicatorElement
    },
    [ACTIVE_REFERENCE_ALERTS]: {
        callback: createIndicatorElement
    },
    [MONETARY_RELIABILITY_GRAPH]: {
        callback: createChart,
        arguments: {
            hideRange: true
        }
    },
    [REQUESTS_TO_TREAT]: {
        callback: createIndicatorElement
    },
    [ORDERS_TO_TREAT]: {
        callback: createIndicatorElement
    },
    [DAILY_HANDLING_INDICATOR]: {
        callback: createIndicatorElement,
        arguments: {route: `handling_index`}
    },
    [DAILY_HANDLING]: {
        callback: createChart
    },
    [DAILY_OPERATIONS]: {
        callback: createChart
    },
    [MONETARY_RELIABILITY_INDICATOR]: {
        callback: createIndicatorElement
    },
    [REFERENCE_RELIABILITY]: {
        callback: createIndicatorElement
    },
    [EXTERNAL_IMAGE]: {
        callback: createExternalImage
    },
    [HANDLING_TRACKING]: {
        callback: createChart
    },
};

/**
 *
 * @param component
 * @param {jQuery} $container
 * @param data
 * @return {boolean}
 */
function renderComponent(component, $container, data) {
    data.__meterKey = component.meterKey;
    $container.empty();

    if(!creators[component.meterKey]) {
        console.error(`No creator function for ${component.meterKey} key.`);
        return false;
    } else {
        const $modal = $container.closest('.modal');
        const isCardExample = $modal.exists();
        if(isCardExample && $modal.exists()) {
            resetColorPickersElementsToForm($modal, data);
            $modal.find(`.component-numbering`).empty();
        }

        const {callback, arguments} = creators[component.meterKey];
        const $element = callback(
            data,
            Object.assign({
                meterKey: component.meterKey,
                rowSize: $container.closest('.dashboard-row').data('size'),
                component: component
            }, arguments || {})
        );

        if($element) {
            $container.html($element);
            const $canvas = $element.find('canvas');
            const $table = $element.find('table');

            if(isCardExample) {
                $modal.find('input[name="jsonConfig"]').remove();
            }

            if($canvas.length > 0) {
                if(!$canvas.hasClass('multiple') && !data.multiple) {
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

function generateAttributes(data, classes) {
    const background = data.backgroundColor ? `background-color:${data.backgroundColor}!important;` : ``;

    return `class="${classes}" style="${background}"`
}

function createTooltip(text) {
    const trimmedText = (text || "").trim();
    if (mode === MODE_EDIT
        || mode === MODE_EXTERNAL
        || !trimmedText) {
        return ``;
    } else {
        return `
            <div class="points has-tooltip" title="${trimmedText}">
                <img src="../../../svg/timeline-information-dashboard.svg" alt="Icône" width="12px">
            </div>
        `;
    }
}

function createPendingRequests(data, {rowSize}) {
    const numberingConfig = {numbering: 0};

    let content = ``;
    let renderNumberingOnce = true;

    generateEditor(data, numberingConfig, [1, 2, 3, 4, 5, 6, 7, 8, 9]);

    let firstIteration = true;
    for(let request of data.requests) {
        content += renderRequest(data, request, rowSize, numberingConfig, firstIteration);
        firstIteration = false;
    }

    return $(`
        <div ${generateAttributes(data, 'dashboard-box dashboard-stats-container h-100')}>
            <div class="title">
                ${applyStyle(data, numberingConfig, 1, data.title)}
            </div>
            ${createTooltip(data.tooltip)}
            <div class="d-flex row no-gutters h-100 overflow-auto overflow-x-hidden pending-request-wrapper">
                ${content}
            </div>
        </div>
    `);
}

function renderRequest(data, request, rowSize, redefinedNumberingConfig, firstIteration) {
    let onCardClick = ``;
    if(!request.href && request.errorMessage) {
        onCardClick = `showBSAlert('${request.errorMessage}', 'danger'); event.preventDefault()`;
    }

    let topRightIcon;
    if(request.topRightIcon === ``) {
        topRightIcon = `<i class="wii-card-icon fa fa-exclamation-triangle red"></i>`
    } else {
        topRightIcon = `<img alt="" src="/svg/${request.topRightIcon}" class="wii-card-icon"/>`;
    }

    const requestUserFirstLetter = request.requestUser.charAt(0).toUpperCase();

    const defaultCardSize = `col-12 col-lg-${mode === MODE_EDIT ? '6' : '4'} col-xl-${mode === MODE_EDIT ? '6' : '3'}`;
    const cardSizeRowSizeMatching = {
        1: 'col-12 col-lg-4 col-xl-3',
        2: 'col-12 col-lg-5',
        3: 'col-12 col-lg-7',
        4: 'col-12 col-lg-10',
        5: 'col-12',
        6: 'col-12',
    }
    const cardSize = cardSizeRowSizeMatching[rowSize] || defaultCardSize;
    const link = mode !== MODE_EDIT && request.href ? `href="${request.href}" onclick="${onCardClick}"` : ``;
    const cursor = mode === MODE_EDIT ? `cursor-default` : ``;

    return `
        <div class="d-flex ${cardSize} p-1">
            <a class="card wii-card request-card pointer p-3 my-2 shadow-sm flex-grow-1 ${cursor} bg-${request.cardColor}" ${link} style="${request.cardBackgroundColor ? (`background-color: ${request.cardBackgroundColor}!important`) : ''}">
                <div class="wii-card-header">
                    <div class="row">
                        <div class="col-10 mb-2">
                            <p class="mb-2 small">${applyStyle(data, redefinedNumberingConfig, 2, request.estimatedFinishTimeLabel, {}, firstIteration)}</p>
                            <strong>${applyStyle(data, redefinedNumberingConfig, 3, request.estimatedFinishTime, {}, firstIteration)}</strong>
                        </div>
                        <div class="col-2 d-flex justify-content-end align-items-start">
                            ${request.emergencyText} ${topRightIcon}
                        </div>
                        <div class="col-12 mb-2">
                            <div class="progress bg-${request.progressBarBGColor}" style="height: 7px;">
                                <div class="progress-bar"
                                     role="progressbar"
                                     style="width: ${request.progress}%; background-color: ${request.progressBarColor};"
                                     aria-valuenow="${request.progress}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <p>${applyStyle(data, redefinedNumberingConfig, 4, $.capitalize(request.requestStatus), {}, firstIteration)}</p>
                        </div>
                    </div>
                </div>
                <div class="wii-card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="card-title text-center">${request.requestBodyTitle}</p>
                        </div>
                        <div class="col-12">
                            <p class="w-100 d-inline-flex justify-content-center">
                                ${applyStyle(data, redefinedNumberingConfig, 6, '<strong class="card-title m-0 mr-2"><i class="fa fa-map-marker-alt"></i></strong><strong class="ellipsis">' + request.requestLocation + '</strong>', {}, firstIteration)}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="wii-card-footer">
                    <div class="row align-items-end">
                        <div class="col-6 text-left ellipsis">
                            <span class="bold">${applyStyle(data, redefinedNumberingConfig, 7, request.requestNumber, {}, firstIteration)}</span><br/>
                            <span class="text-secondary">${applyStyle(data, redefinedNumberingConfig, 8, request.requestDate, {}, firstIteration)}</span>
                        </div>
                        <div class="col-6 text-right ellipsis">
                            <div class="profile-picture" style="background-color: #EEE">${requestUserFirstLetter}</div>
                            <span class="bold">${applyStyle(data, redefinedNumberingConfig, 9, request.requestUser, {}, firstIteration)}</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    `;
}

function createEntriesToHandleElement(data, {meterKey}) {
    if(!data) {
        console.error(`Invalid data for entries element.`);
        return false;
    }
    const numberingConfig = {numbering: 0};
    const $graph = createChart(data, {route: null, variable: null, cssClass: 'multiple'}, true, numberingConfig);
    const $firstComponent = $('<div/>', {

        class: `w-100 pb-1 flex-fill dashboard-component h-100 mx-0 mt-0`,
        html: createIndicatorElement(
            Object.assign(data || {}, {
                title: 'Nombre de lignes à traiter',
                tooltip: data.linesCountTooltip,
                count: data.count,
                componentLink: data.componentLink,
                backgroundColor: data.backgroundColor || undefined,
            }),
            {
                meterKey,
                customContainerClass: 'overflow-hidden',
                titleBackendNumber: 2,
                valueNumber: 3,
            },
            numberingConfig
        )
    });
    const $secondComponent = $('<div/>', {
        class: `w-100 pt-1 flex-fill dashboard-component h-100 mx-0 mb-0`,
        html: createIndicatorElement(
            Object.assign(data || {},{
                title: 'Prochain emplacement à traiter',
                tooltip: data.nextLocationTooltip,
                count: data.nextLocation,
                componentLink: data.componentLink,
                backgroundColor: data.backgroundColor || undefined,
            }),
            {
                meterKey,
                customContainerClass: 'overflow-hidden',
                titleBackendNumber: 4,
                valueNumber: 5,
            },
            numberingConfig
        )
    });

    let $container;
    if ($.mobileCheck()) {
        $container = $('<div/>', {class: 'dashboard-box'});
    }

    const $content = $('<div/>', {
        class: 'row w-100 mx-0 h-100 no-gutters',
        html: [
            $('<div/>', {
                class: 'col-12 col-lg-9 pr-lg-2 dashboard-component-column dashboard-component m-lg-0',
                html: $graph
            }),
            $('<div/>', {
                class: 'col-12 col-lg-3 pl-lg-2 dashboard-component-column dashboard-component dashboard-component-split-horizontally m-0',
                html: $('<div/>', {
                    class: 'h-100 d-flex flex-column',
                    html: [
                        $firstComponent,
                        $secondComponent
                    ]
                })
            })
        ]
    });

    if ($container) {
        $container.html($content);
    }
    else {
        $container = $content;
    }

    return $container;
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
    const numberingConfig = {numbering: 0};

    generateEditor(data, numberingConfig, [1, 2, 3]);

    const table = `<table class="table display retards-table" id="${Math.floor(Math.random() * Math.floor(10000))}"></table>`;
    let content = table;
    if ($('.modal.show').exists()) {
        content = `
            <div class="row mx-0">
                <div class="col-auto pr-0">
                    <div class="row mb-5 mt-3">
                        ${applyStyle(data, numberingConfig, 2)}
                    </div>
                    <div class="row">
                        ${applyStyle(data, numberingConfig, 3)}
                    </div>
                </div>
                <div class="col pl-0">
                    ${table}
                </div>
            </div>`;
    }
    return $(`
        <div ${generateAttributes(data, 'dashboard-box dashboard-stats-container')}>
            <div class="title">
                ${applyStyle(data, numberingConfig, 1, title)}
            </div>
            ${createTooltip(data.tooltip)}
            ${content}
        </div>
    `);
}

function calculateChartsFontSize() {
    let width = Math.max(document.body.clientWidth, 1500);
    return Math.floor(width / 120);
}

/**
 * @param {*} data
 * @param {{route: string|null, variable: string|null}} pagination
 * @return {boolean|jQuery}
 */
function createChart(data, {route, cssClass, hideRange} = {route: null, cssClass: null, hideRange: false}, redefinedNumbering = false, redefinedNumberingConfig = null) {
    if(!data) {
        console.error(`Invalid data for "${data.title}"`);
        return false;
    }

    const hasRangeButton = (route && !hideRange && mode !== MODE_EDIT && mode !== MODE_EXTERNAL);

    const dashboardBoxContainerClass = hasRangeButton
        ? 'dashboard-box-container-title-content'
        : 'dashboard-box-container-title-content-rangeButton w-100';
    const numberingConfig = {numbering: 0};

    const title = data.title || "";

    const pagination = hasRangeButton
        ? `
            <div class="range-buttons">
                <div class="arrow-chart"
                     onclick="drawChartWithHisto($(this), '${route}', 'before')">
                    <i class="fas fa-chevron-left pointer"></i>
                </div>
                <span class="firstDay" data-day="${data.firstDayData}">${data.firstDay}</span> -
                <span class="lastDay" data-day="${data.lastDayData}">${data.lastDay}</span>
                <div class="arrow-chart"
                     onclick="drawChartWithHisto($(this), '${route}', 'after')">
                    <i class="fas fa-chevron-right pointer"></i>
                </div>
            </div>
        `
        : '';


    return $(`
        <div ${generateAttributes(data, 'dashboard-box dashboard-stats-container ' + dashboardBoxContainerClass)}>
            <div class="title">
                ${withStyle(data, redefinedNumberingConfig || numberingConfig, 1, title.split('(')[0])}
            </div>
            ${createTooltip(data.chartData.hint || data.tooltip)}
            <div class="flex-fill content">
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

    const carriers = Array.isArray(data.carriers) ? data.carriers.join(', ') : data.carriers;
    const title = data.title || "";
    const numberingConfig = {numbering: 0};

    return $(`
        <div ${generateAttributes(data, 'dashboard-box dashboard-stats-container')}>
            <div class="title">
                ${withStyle(data, numberingConfig, 1, title)}
            </div>
            ${createTooltip(data.tooltip)}
            <h1 class="scroll">${withStyle(data, numberingConfig, 2, carriers)}</h1>
        </div>
    `);
}

/**
 * @param {*} data
 * @param config
 * @param redefinedNumberingConfig
 * @return {boolean|jQuery}
 */
function createIndicatorElement(data, config, redefinedNumberingConfig = null) {
    let meterKey = config.meterKey;
    let customContainerClass = config.customContainerClass;
    const redirectToHandling = config.component?.config?.redirectToHandling;
    const redirectToHandlingRoute = redirectToHandling && config.component?.config?.selectedDate && config.route ? Routing.generate(config.route, {
        date: new Date().toISOString().split('T')[0],
    }) : '';
    let remainingConfig = Object.assign({}, config);
    delete remainingConfig.meterKey;
    delete remainingConfig.customContainerClass;

    if(!data || data.count === undefined) {
        console.error('Invalid data for ' + (meterKey || '-').replaceAll('_', ' ') + ' element.');
        return false;
    }
    customContainerClass = customContainerClass || '';
    const {title, subtitle, tooltip, count, delay, componentLink, emergency, subCounts, backgroundColor} = data;
    const element = componentLink || redirectToHandlingRoute ? '<a/>' : '<div/>';
    const customAttributes = componentLink || redirectToHandlingRoute
        ? {
            href: componentLink ?? redirectToHandlingRoute,
            target: '_blank'
        }
        : {};
    const clickableClass = componentLink || redirectToHandlingRoute ? 'pointer' : '';
    const needsEmergencyDisplay = emergency && count > 0;
    const $logoTag = data.logoURL ? `<img src="${data.logoURL}" class="w-px-30 h-px-30" style="object-fit: contain"/>` : '';
    const $emergencyIcon = needsEmergencyDisplay ? '<i class="fa fa-exclamation-triangle red"></i>' : $logoTag;
    const numberingConfig = {numbering: 0};
    const smartNumberingConfig = redefinedNumberingConfig ? redefinedNumberingConfig : numberingConfig;
    const randomId = guidGenerator();

    const $element = $(element, Object.assign({
        class: `dashboard-box dashboard-box-indicator text-center dashboard-stats-container ${customContainerClass}`,
        style: mode === MODE_EDIT ? `` : `${backgroundColor ? 'background-color:' + backgroundColor : ''}`,
        html: [
            createTooltip(tooltip),
            title
                ? $('<div/>', {
                    class: `text-center`,
                    html: [
                        `<span class="title">
                            ${$emergencyIcon}
                            ${withStyle(data, smartNumberingConfig, remainingConfig.titleBackendNumber || 1, title)}
                            ${$emergencyIcon}
                         </span>`,
                        `<p class="small scroll location-label">
                            ${subtitle ? withStyle(data, smartNumberingConfig, 2, subtitle) : ''}
                         </p>`
                    ]
                })
                : undefined,
            subtitle && !title
                ? $('<div/>', {
                    class: 'location-label scroll small',
                    html: withStyle(data, smartNumberingConfig, 2, subtitle)
                })
                : undefined,
            (() => {
                if(count === undefined) {
                    return undefined;
                }

                generateEditor(data, smartNumberingConfig, remainingConfig.valueNumber || 3, count);

                return $('<div/>', {
                    class: `align-items-center`,
                    html: `<div class="${clickableClass} dashboard-stats dashboard-stats-counter scroll">
                    ${((count || count === '0' || count === 0) ? (applyStyle(
                        data,
                        smartNumberingConfig,
                        remainingConfig.valueNumber || 3,
                        count,
                        needsEmergencyDisplay ? OVERRIDE_FONT_RED : {}
                    ))  : '-')}</div>`,
                });
            })(),
            delay
                ? $('<div/>', {
                    class: `text-center title dashboard-stats-delay-title`,
                    html: withStyle(
                        data,
                        smartNumberingConfig,
                        4,
                        delay < 0 ? Translation.of('Dashboard', 'Retard') + ' : ' : Translation.of('Dashboard', 'A traiter sous') + ' : ',
                        delay < 0 ? OVERRIDE_FONT_RED : {}
                    ),
                })
                : undefined,
            delay
                ? $('<div/>', {
                    class: `${clickableClass} dashboard-stats dashboard-stats-delay`,
                    html: withStyle(
                        data,
                        smartNumberingConfig,
                        5,
                        !isNaN(Math.abs(delay)) ? renderMillisecondsToDelay(Math.abs(delay), 'display') : delay,
                        delay < 0 ? OVERRIDE_FONT_RED : {}
                    )
                })
                : undefined,
            ...((subCounts || [])
                .filter(Boolean)
                .map((subCount) => (
                    $('<div/>', {
                        class: `${clickableClass} dashboard-stats`,
                        html: subCount ? withStyle(data, smartNumberingConfig, 9 + subCounts.indexOf(subCount), subCount) : ''
                    })
                )))
        ].filter(Boolean)
    }, customAttributes));

    if (Number.isInteger(delay)) {
        $element.data('delay', delay);
        $element.attr('id', randomId);
    }

    return $element;
}

function createExternalImage(data, config) {
    if(!data) {
        console.error('Invalid data for external image element.');
        return false;
    }

    let url;
    if(config.component.config) {
        url = config.component.config.url;
    } else {
        url = '/img/mobile_logo_header.svg';
    }

    return $(`
        <div ${generateAttributes(data, 'dashboard-box dashboard-image-container')}>
            <img src="${url}" style="width:100%;height:auto;max-height:100%;object-fit: contain;" alt="Composant image">
        </div>
    `);
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

function drawChartWithHisto($button, path, beforeAfter = 'now') {
    let $dashboardBox = $button.closest('.dashboard-box');
    let $rangeBtns = $dashboardBox.find('.range-buttons');
    let $firstDay = $rangeBtns.find('.firstDay');
    let $lastDay = $rangeBtns.find('.lastDay');
    let $canvas = $dashboardBox.find('canvas');
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

        createAndUpdateSimpleChart($canvas, null, data);
    });
}


function updateSimpleChartData(chart, data, label, stack = false,
                               {data: subData, label: lineChartLabel} = {data: undefined, label: undefined}, chartColors = []) {
    chart.data.datasets = [{data: [], label}];
    chart.data.labels = [];
    if(data.hint) {
        delete data.hint;
    }
    const dataKeys = Object.keys(data).filter((key) => key !== 'stack');

    for(const key of dataKeys) {
        chart.data.labels.push(key);
        chart.data.datasets[0].data.push(data[key]);
    }

    const dataLength = chart.data.datasets[0].data.length;
    if(dataLength > 0) {
        const color = chartColors.length > 0 ? chartColors[0] : undefined;
        chart.data.datasets[0].backgroundColor = new Array(dataLength);
        chart.data.datasets[0].backgroundColor.fill(color);
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
        (data.stack || []).forEach((stack, index) => {
            let stackCopied = $.deepCopy(stack);
            if (chartColors[index+1] && !data.colorsFilled) {
                stackCopied.backgroundColor = chartColors[index+1];
            }
            chart.data.datasets.push(stackCopied);
        });
    }

    chart.update();
}

function createAndUpdateSimpleChart($canvas, chart, data, forceCreation = false, disableAnimation = false) {
    if(forceCreation || !chart) {
        chart = newChart($canvas, data, false, disableAnimation);
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
            },
            data.chartColors || []
        );
    }

    return chart;
}

function newChart($canvasId, data, redForLastData = false, disableAnimation = false) {
    if($canvasId.length) {
        const fontSize = currentChartsFontSize;

        return new Chart($canvasId, {
            type: 'bar',
            data: {},
            options: {
                layout: {
                    padding: {
                        top: 30
                    }
                },
                tooltips: data.multiple && data.stackValues  ?
                    {
                        position: 'middle',
                        callbacks: {
                            title: function(){
                                return null;
                            },
                            label: function(t, d) {
                                var typeLabel = d.datasets[t.datasetIndex].label;
                                var typeQuantity = t.yLabel;
                                return typeLabel + ': ' + typeQuantity;
                            },
                        },
                        xAlign: 'center',
                        yAlign: 'center',
                    } : false,
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom',
                    labels: {
                        fontSize,
                        filter: function(item) {
                            return Boolean(item && item.text);
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontSizeYAxes,
                            beginAtZero: true,
                            callback: (value) => {
                                if(Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        },
                        stacked: data.stackValues,
                    }],
                    xAxes: [{
                        ticks: {
                            fontSize
                        },
                        stacked: data.stackValues,
                    }]
                },
                hover: data.multiple && data.stackValues ?
                    {
                        mode: 'nearest',
                        intersect: true,
                    } : true,
                animation: {
                    duration: disableAnimation ? 0 : 1000,
                    onComplete() {
                        buildLabelOnBarChart(this, redForLastData);
                    }
                }
            }
        });
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
        processing: true,
        order: [['delay', 'desc']],
        columns: [
            {
                data: 'pack',
                name: 'pack',
                title: applyStyle(data, null, 2, 'Colis', false),
                render: text => applyStyle(data, null, 3, text, false),
            },
            {
                data: 'date',
                name: 'date',
                title: applyStyle(data, null, 2, 'Dépose', false),
                render: text => applyStyle(data, null, 3, text, false),
            },
            {
                data: 'delay',
                name: 'delay',
                title: applyStyle(data, null, 2, 'Délai', false),
                render: (milliseconds, type) => applyStyle(data, null, 3, renderMillisecondsToDelay(milliseconds, type), false)
            },
            {
                data: 'location',
                name: 'location',
                title: applyStyle(data, null, 2, 'Emplacement', false),
                render: text => applyStyle(data, null, 3, text, false)
            },
        ],
        "drawCallback": function() {
            let $dataTable = $table.dataTable();
            $dataTable.fnAdjustColumnSizing(false);

            // TableTools
            if (typeof(TableTools) != "undefined") {
                let tableTools = TableTools.fnGetInstance(table);
                if (tableTools != null && tableTools.fnResizeRequired()) {
                    tableTools.fnResizeButtons();
                }
            }
            //
            let $dataTableWrapper = $table.closest(".dataTables_wrapper");
            let panelHeight = $dataTableWrapper.parent().height();
            let toolbarHeights = 0;
            $dataTableWrapper.find(".fg-toolbar").each(function(i, obj) {
                toolbarHeights = toolbarHeights + $(obj).height();
            });

            let scrollHeadHeight = $dataTableWrapper.find(".dataTables_scrollHead").height();
            let height = panelHeight - toolbarHeights - scrollHeadHeight;
            $dataTableWrapper.find(".dataTables_scrollBody").height(height);

            $dataTable._fnScrollDraw();
        }
    };
    if(mode === MODE_EDIT) {
        datatableColisConfig.data = data.tableData;
    } else {
        datatableColisConfig.ajax = {
            "url": Routing.generate('api_late_pack', true),
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
        if(data.chartData) {
            delete data.chartData.hint;
        }
        chart = newChart($canvas, data, redForLastData, disableAnimation);
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

/**
 * Applies the style without adding font editors
 * to the component edition modal.
 * Useful for stylable elements that are displayed
 * multiple times in the same component.
 */
function applyStyle(data, numberingConfig, backendNumber, value = ``, overrides = {}, generateSuperscript = true) {
    const fontSize = data['fontSize-' + backendNumber] || null;
    const textColor = overrides.color || (data['textColor-' + backendNumber] || null);
    const textBold = data['textBold-' + backendNumber]  ? 'checked' : '';
    const textItalic = data['textItalic-' + backendNumber] ? 'checked' : '';
    const textUnderline = data['textUnderline-' + backendNumber] ? 'checked' : '';
    let text = ``;
    if(generateSuperscript && numberingConfig && $('#modalComponentTypeSecondStep').hasClass('show')) {
        text = `<sup>(${numberingConfig.associations[backendNumber]})</sup>`;
    }
    let style = `white-space:nowrap;`;

    if(fontSize !== null) {
        style += `font-size: ${fontSize}pt;`;
    }

    if(textColor !== null) {
        style += `color: ${textColor};`;
    }

    if(textBold === `checked`) {
        style += `font-weight: bold;`;
    }

    if(textItalic === `checked`) {
        style += `font-style: italic;`;
    }

    if(textUnderline === `checked`) {
        style += `text-decoration: underline;`;
    }

    return text + `<span style="${style}">${value}</span>`;
}

/**
 * Create the editor elements in the edition modal.
 */
function generateEditor(data, numberingConfig, backendNumbers) {
    if(!numberingConfig) {
        return;
    }

    if(!numberingConfig.associations) {
        numberingConfig.associations = {};
    }

    if(!Array.isArray(backendNumbers)) {
        backendNumbers = [backendNumbers];
    }

    for(const number of backendNumbers) {
        const $container = $(`.modal.show[data-meter-key="${data.__meterKey}"] .component-numbering`);
        const fontSize = data['fontSize-' + number] || ``;
        const textColor = data['textColor-' + number] || "#FFFFFF";
        const textBold = data['textBold-' + number] ? 'checked' : '';
        const textItalic = data['textItalic-' + number] ? 'checked' : '';
        const textUnderline = data['textUnderline-' + number] ? 'checked' : '';

        numberingConfig.numbering += 1;
        numberingConfig.associations[number] = numberingConfig.numbering;

        $container.append(`
            <div class="d-flex align-items-center p-1" data-number="${number}">
                <sup class="pt-2">(${numberingConfig.numbering})</sup>
                <input type="number" data-no-arrow class="data form-control w-px-70 mr-2" name="fontSize-${number}" value="${fontSize}">
                <input type="color" class="data form-control needed w-px-50 mr-2" name="textColor-${number}" value="${textColor}">
                <label class="text-bold-selector">
                    <input type="checkbox" name="textBold-${number}" class="data checkbox" ${textBold}>
                    <i></i>
                </label>
                <label class="text-italic-selector">
                    <input type="checkbox" name="textItalic-${number}" class="data checkbox" ${textItalic}>
                    <i></i>
                </label>
                <label class="text-underline-selector">
                    <input type="checkbox" name="textUnderline-${number}" class="data checkbox" ${textUnderline}>
                    <i></i>
                </label>
            </div>
        `);
    }
}

/**
 * Applies the style and add font editors
 * to the component edition modal.
 * Useful for text that is only rendered once
 * such as title.
 */
function withStyle(data, numberingConfig, backendNumber, value, overrides = {}) {
    generateEditor(data, numberingConfig, backendNumber);
    return applyStyle(data, numberingConfig, backendNumber, value, overrides);
}

function generateColorPickerElement(data, key = 0) {
    return $(`<div/>`, {
        class: 'd-flex justify-content-between align-items-center ml-5',
        html: $(`<input/>`, {
            type: `color`,
            class: `data-array form-control needed w-50 chart-color-picker`,
            'data-id': key,
            name: `chartColors`,
            value: data.chartColors[key]
        })
    }).prepend($(`<span/>`, {
        text: (data.chartColorsLabels && data.chartColorsLabels[key] !== undefined) ? data.chartColorsLabels[key] : key
    }));
}

function resetColorPickersElementsToForm($modal, data) {
    const $chartColorPickersContainer = $modal.find('.chart-color-pickers');
    const $colorPickerAccordion = $modal.find('.color-picker-accordion');

    $chartColorPickersContainer.empty();
    $colorPickerAccordion.removeClass('d-none');

    if(data.separateType) {
        if ((data.chartColors && (data.handlingTypes || data.dispatchTypes))) {
            for (let key in data.chartColors) {
                $chartColorPickersContainer.append(generateColorPickerElement(data, key));
            }
        } else {
            $colorPickerAccordion.addClass('d-none');
        }
    } else {
        if (data.chartColors) {
            const chartColorKeys = Object.keys(data.chartColors);
            for (const key of chartColorKeys) {
                $chartColorPickersContainer.append(generateColorPickerElement(data, key));
            }
        }
    }
}

function hideOrShowStackButton(){
    $('.stack-button').toggleClass('d-none');
    $('input[name="stackValues"]').prop('checked', false);
}

Chart.Tooltip.positioners.middle = elements => {
    let model = elements[0]._model;
    return {
        x: model.x,
        y: (model.base + model.y) / 2
    };
};

function guidGenerator() {
    let S4 = function() {
        return (((1+Math.random())*0x10000)|0).toString(16).substring(1);
    };
    return (S4()+S4()+"-"+S4()+"-"+S4()+"-"+S4()+"-"+S4()+S4()+S4());
}
