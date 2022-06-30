let noMapData = true;
let noChartData = true;
const $errorContainer = $('.no-monitoring-data');
$(document).ready(() => {
    initData();

    const $timelineContainer = $('.timeline-container');
    if ($timelineContainer.exists()) {
        $timelineContainer.each(function () {
            initTimeline($(this));
        });
    }

    const $editEndButton = $(`[data-target="#modalEditPairingEnd"]`);
    if ($editEndButton.exists()) {
        $editEndButton.click(function () {
            modalEditPairingEnd.find(`input[name="id"]`).val($(this).data(`id`));
        });

        const modalEditPairingEnd = $("#modalEditPairingEnd");
        const submitEditPairingEnd = $("#submitEditPairingEnd");
        const urlEditPairingEnd = Routing.generate('pairing_edit_end', {});
        InitModal(modalEditPairingEnd, submitEditPairingEnd, urlEditPairingEnd, {
            success: response => {
                if (!$(response.selector).exists()) {
                    $(`.pairing-dates-content`).append(`
                        <br/><br/>
                        <span class="pairing-date-prefix">Fin le : </span><br/>
                        <span class="date-prefix pairing-end-date-${response.id}"></span>
                    `);
                }

                $(response.selector).text(response.date);
            }
        });
    }
});

function initMapCall(callback) {
    const $maps = $(`[data-map]`);
    if ($maps.length > 0) {
        $maps.each((i, elem) => initMap(elem, callback));
    } else {
        callback();
    }
}

function initChartCall(callback) {
    const $charts = $(`[data-chart]`);
    if ($charts.length > 0) {
        $charts.each((i, elem) => initLineChart(elem, callback));
    } else {
        callback();
    }
}

function noMonitoringData() {
    $errorContainer.empty();
    const $emptyResult = $(`<div/>`, {
        class: `d-flex flex-column align-items-center`,
        html: [
            $(`<p/>`, {
                class: `h4`,
                text: 'Aucune donnée'
            }),
            $(`<i/>`, {
                class: `fas fa-frown fa-4x`
            })
        ]
    });

    $errorContainer.removeClass('d-none');
    $errorContainer.append($emptyResult).hide().fadeIn(600);
}

function initData() {
    initMapCall(() => {
        initChartCall(() => {
            if (noChartData && noMapData) {
                noMonitoringData();
            }
        });
    });
}

function filter() {
    initData();
}

function unpair(pairing) {
    $.post(Routing.generate(`unpair`, {pairing}), function (response) {
        if (response.success) {
            window.location.href = Routing.generate(`pairing_index`);
        }
    })
}

function getFiltersValue() {
    return {
        start: $(`input[name="start"]`).val(),
        end: $(`input[name="end"]`).val(),
    };
}

let previousMap = null;

function initMap(element, callback) {
    const $element = $(element);
    $errorContainer.addClass('d-none');

    $.get($element.data(`fetch-url`), getFiltersValue(), function (response) {
        $(`.data-loader`).remove();
        if (previousMap) {
            previousMap.off();
            previousMap.remove();
        }

        let map = Leaflet.map(element).setView([44.831598, -0.577096], 13);
        previousMap = map;

        Leaflet.tileLayer('https://tiles.stadiamaps.com/tiles/osm_bright/{z}/{x}/{y}{r}.png', {
            maxZoom: 20,
            attribution:
                '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a>,' +
                ' &copy; <a href="https://openmaptiles.org/">OpenMapTiles</a> ' +
                '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors'
        }).addTo(map);

        let sensors = Object.keys(response);

        const responseValues = Object.values(response);
        // hide the map if there are no sensors
        $element.closest('.wii-page-card').toggle(true);
        noMapData = false;
        if (responseValues.length > 0) {
            smartFitBounds(responseValues, map, Leaflet.latLngBounds());
            const markersToDisplay = smartFilterCoordinates(sensors, response);
            smartDisplayCoordinates(markersToDisplay, map);
            callback();
        } else {
            noMapData = true;
            callback();
            $element.closest('.wii-page-card').toggle(false);
        }
    });
}

function smartFitBounds(responseValues, map, globalBounds) {
    responseValues.forEach(((date) => {
        Object.values(date).forEach((coordinates) => {
            globalBounds.extend(coordinates);      // Extend LatLngBounds with coordinates
        });
    }));

    map.fitBounds(globalBounds);
}

function smartFilterCoordinates(sensors, response) {
    const last = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [50, 82],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    let smartCoordinates = [];
    let lastMarker = null;
    sensors.forEach((sensor, sensorsIteration) => {
        const dates = Object.keys(response[sensor]);
        dates.forEach((label, iteration) => {
            const coordinates = response[sensor][label];
            const isLastIteration = iteration === dates.length - 1 && sensorsIteration === sensors.length - 1;
            let marker = Leaflet
                .marker(coordinates, isLastIteration ? {icon: last} : {})
                .on('click', function () {
                    this.bounce(1);
                });
            lastMarker = marker;
            marker.bindPopup(`Capteur : ${sensor} <br> Date et heure : ${label}`);
            smartCoordinates.push(marker);
        });
    });
    return smartCoordinates;
}

function smartDisplayCoordinates(markers, map) {
    let markerClusterGroup = L.markerClusterGroup({
        disableClusteringAtZoom: 19,
        spiderfyOnMaxZoom: false,
    });

    let polyline = [];
    markers.forEach((marker, iteration) => {
        marker.bounce(1);
        markerClusterGroup.addLayer(marker);
        polyline.push(marker.getLatLng());
        const isLastIteration = iteration === markers.length - 1;
        if (isLastIteration) {
            Leaflet
                .polyline(polyline, {color: 'blue', snakingSpeed: 500})
                .addTo(map)
                .snakeIn()
                .on('snakeend', function () {
                    let antPolyline = new Leaflet.Polyline.AntPath(polyline, {
                        color: 'blue',
                        delay: 400,
                        dashArray: [
                            100,
                            100
                        ]
                    });
                    antPolyline.addTo(map);
                    if (isLastIteration) {
                        map.fitBounds([marker.getLatLng()]);
                    }
                });
        }
    });
    map.addLayer(markerClusterGroup);
}

function initLineChart(element, callback) {
    const $element = $(element);
    $errorContainer.addClass('d-none');

    $.get($element.data(`fetch-url`), getFiltersValue(), function (response) {
        $(`.data-loader`).remove();
        let data = {
            datasets: [],
            labels: []
        };
        let sensorDates = Object.keys(response).filter((key) => key !== 'colors');
        const sensors = Object.keys(response['colors']);
        let datasets = {};

        // hide the chart if there are no sensors
        $element.closest('.wii-page-card').toggle(true);
        noChartData = false;
        let lineDataMax = [];
        let lineDataMin = [];
        sensorDates.forEach((date) => {
            data.labels.push(date);
            sensors.forEach((sensor) => {
                const value = response[date][sensor] || null;
                let dataset = datasets[sensor] || {
                    label: sensor,
                    fill: false,
                    data: [],
                    borderColor: response.colors[sensor],
                    tension: 0.1
                };
                dataset.data.push(value);
                datasets[sensor] = dataset;
            });
            if ($element.data('needsline')) {
                if ($element.data('mintemp') && $element.data('maxtemp')) {
                    lineDataMax.push($element.data('maxtemp'));
                    lineDataMin.push($element.data('mintemp'));
                }
            }
        });
        if (lineDataMax.length === 1 && lineDataMin.length === 1) {
            lineDataMin = [lineDataMin[0], lineDataMin[0]];
            lineDataMax = [lineDataMax[0], lineDataMax[0]];
        }
        if ($element.data('needsline')) {
            datasets['lineDataMax'] = {
                data: lineDataMax[0] > lineDataMin[0] ? lineDataMax : lineDataMin,
                pointRadius: 0,
                pointHitRadius: 0,
                borderColor: '#F00',
                fill: false,
            };

            datasets['lineDataMin'] = {
                data: lineDataMax[0] < lineDataMin[0] ? lineDataMax : lineDataMin,
                pointRadius: 0,
                pointHitRadius: 0,
                borderColor: '#00F',
                fill: false,
            };
        }
        data.datasets = Object.values(datasets);
        const config = {
            type: 'line',
            data,
            options: {
                legend: {
                    labels: {
                        filter: function (item, chart) {
                            return item.datasetIndex < data.datasets.length - 2;
                        }
                    }
                },
                maintainAspectRatio: false,
                spanGaps: true,
                scales: {
                    xAxes: [{
                        ticks: {
                            callback: (label) => {
                                if (/\s/.test(label)) {
                                    return label.split(` `);
                                } else {
                                    return label;
                                }
                            }
                        }
                    }],
                    ...($element.data('needsline') ? {
                        yAxes: [{
                            ticks: {
                                min: lineDataMin.length !== 0 && lineDataMin.length !== 0 ? Math.min(lineDataMax[0], lineDataMin[0])-5 : 0,
                                max: lineDataMin.length !== 0 && lineDataMax.length !== 0 ? Math.max(lineDataMax[0], lineDataMin[0])+5 : 1,
                            }
                        }]
                    } : {})
                }
            }
        }
        let chart = new Chart($element, config);
        $element.closest('.wii-page-card:not(.always-visible)').toggle(sensors.length > 0);
        if (sensors.length === 0) {
            noChartData = true;
            callback();
        }
    });
}

function initSteppedLineChart($element, labels, values, tooltips, label) {
    const zeroAxis = Array(values.length);
    zeroAxis.fill(0, 0, values.length);

    const config = {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label,
                    data: values,
                    borderColor: '#98A8EB',
                    fill: false,
                    steppedLine: true,
                    pointRadius: 4,
                    pointBackgroundColor: "#fff",
                    pointBorderColor: '#000',
                    borderWidth: 1.5
                },
                {
                    data: zeroAxis,
                    pointRadius: 0,
                    pointHitRadius: 0,
                    borderColor: '#666666',
                    fill: false,
                    borderWidth: 2,
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            legend: {
                position: 'bottom',
                labels: {
                    filter: function (item) {
                        return item.datasetIndex < 1;
                    }
                }
            },
            tooltips: {
                backgroundColor: '#666666',
                titleAlign: 'center',
                bodyAlign: 'center',
                displayColors: false,
                enabled: true,
                mode: 'single',
                callbacks: {
                    label: function (tooltipItems) {
                        if (tooltips[tooltipItems.label]) {
                            return Array.isArray(tooltips[tooltipItems.label])
                                ? tooltips[tooltipItems.label].concat([label + ' : ' + tooltipItems.value])
                                : [tooltips[tooltipItems.label]].concat([label + ' : ' + tooltipItems.value])
                        } else {
                            return label + ' : ' + tooltipItems.value;
                        }
                    }
                }
            },
            scales: {
                yAxes: [{
                    display: true,
                    ticks: {
                        suggestedMin: Math.min(...values) - 5,
                    }
                }]
            },
            responsive: true,
            interaction: {
                intersect: false,
                axis: 'x'
            },
        }
    };
    const existing = Object.values(Chart.instances).filter((c) => c.canvas.id === $element.attr('id')).pop();
    if (existing) {
        existing.destroy();
    }
    return new Chart($element, config);
}

function initTimeline($timelineContainer, showMore = false) {
    $timelineContainer.pushLoader('black', 'normal');

    const timelineDataPath = $timelineContainer.data('timeline-data-path');
    const ended = $timelineContainer.data('timeline-end');
    const $oldShowMoreButton = $timelineContainer.find('.timeline-show-more-button');

    if (!showMore) {
        $timelineContainer.find('.timeline-row').remove();
    }

    if (!ended) {
        const start = $timelineContainer.find('.timeline-row:not(.timeline-show-more-button-container)').length;
        const firstLoading = start === 0;
        if (firstLoading) {
            $timelineContainer
                .removeClass('py-3')
                .addClass('py-5');
        }

        $
            .get(timelineDataPath, {start})
            .then(({data, isEnd, isGrouped}) => {
                $timelineContainer.data('timeline-end', Boolean(isEnd));
                if ($oldShowMoreButton.exists()) {
                    $oldShowMoreButton.parent().remove();
                }

                const timeline = data || [];
                const $timeline = timeline.map(({title, titleHref, active, group, groupHref, datePrefix, date}, index) => {
                    const lastClass = (isEnd && index === 0) ? 'last-timeline-cell' : '';
                    const activeClass = active ? 'timeline-cell-active' : '';
                    const largeTimelineCellClass = !isGrouped ? 'timeline-cell-large' : '';
                    const groupAsLink = (group && groupHref);

                    return $('<div/>', {
                        class: 'timeline-row',
                        html: [
                            isGrouped
                                ? $(!groupAsLink ? '<div/>' : '<a/>', {
                                    class: `timeline-cell timeline-cell-left ${lastClass}`,
                                    ...(group
                                        ? {text: group}
                                        : {}),
                                    ...(groupAsLink
                                        ? {href: groupHref}
                                        : {})
                                })
                                : undefined,
                            $('<div/>', {
                                class: `timeline-cell timeline-cell-right ${lastClass} ${activeClass} ${largeTimelineCellClass}`,
                                html: [
                                    ...(title
                                        ? [
                                            (active && titleHref)
                                                ? `<a href="${titleHref}" class="timeline-cell-title">${title}</a>`
                                                : `<span class="timeline-cell-title">${title}</span>`,
                                            `<br/>`,
                                        ]
                                        : []),
                                    ...(datePrefix
                                        ? [
                                            `<span class="pairing-date-prefix">${datePrefix}</span>`,
                                            `<br/>`
                                        ]
                                        : []),
                                    ...(date
                                        ? [`<span class="pairing-date">${date}</span>`]
                                        : []),

                                ]
                            })
                        ]
                    })
                });
                $timelineContainer.prepend($timeline);

                if (firstLoading) {
                    $timelineContainer
                        .addClass('py-3')
                        .removeClass('py-5');
                }

                if (!isEnd && timeline.length > 0) {
                    $timelineContainer.prepend(
                        $('<div/>', {
                            class: 'timeline-row timeline-show-more-button-container justify-content-center pb-4',
                            html: $('<button/>', {
                                class: 'btn btn-outline-info timeline-show-more-button',
                                text: 'Voir plus',
                                click: () => {
                                    initTimeline($timelineContainer, true);
                                }
                            })
                        })
                    );
                }

                $timelineContainer.popLoader();
            });
    }
}
