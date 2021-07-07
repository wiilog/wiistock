let noMapData = true;
let noChartData = true;
const $errorContainer = $('.no-monitoring-data');
$(document).ready(() => {
    initData();

    const $timelineContainer = $('.timeline-container');
    if ($timelineContainer.exists()) {
        $timelineContainer.each(function() {
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
                if(!$(response.selector).exists()) {
                    console.log("ok")
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
    console.log($charts);
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
        if(previousMap) {
            previousMap.off();
            previousMap.remove();
        }

        let map = Leaflet.map(element).setView([44.831598, -0.577096], 13);
        previousMap = map;

        Leaflet.tileLayer('https://{s}.tile.thunderforest.com/spinal-map/{z}/{x}/{y}.png?apikey=f03db101993a48f0b61ba35c1165f2ab', {
            attribution:
                '&copy; <a href="http://www.thunderforest.com/">Thunderforest</a>,' +
                ' &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            apikey: 'f03db101993a48f0b61ba35c1165f2ab',
            maxZoom: 22
        }).addTo(map);

        let sensors = Object.keys(response);
        let index = 0;

        let globalBounds = Leaflet.latLngBounds();

        const responseValues = Object.values(response);
        // hide the map if there are no sensors
        $element.closest('.wii-page-card').toggle(true);
        noMapData = false;
        if(responseValues.length > 0) {
            responseValues.forEach(((date) => {
                Object.values(date).forEach((coordinates) => {
                    globalBounds.extend(coordinates);      // Extend LatLngBounds with coordinates
                });
            }));

            map.fitBounds(globalBounds);

            sensors.forEach((sensor) => {
                const dates = Object.keys(response[sensor]);
                let polyline = [];
                dates.forEach((label, iteration) => {
                    const coordinates = response[sensor][label];
                    polyline.push(coordinates);
                    index++;
                    setTimeout(() => {
                        Leaflet
                            .marker(coordinates)
                            .addTo(map)
                            .bounce(1)
                            .on('click', function () {
                                this.bounce(1);
                            })
                            .bindPopup(`Capteur : ${sensor} <br> Date et heure : ${label}`);
                        if (iteration === dates.length - 1 && dates.length > 1) {
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
                                });
                        }
                    }, 200 * index);
                });
            });

            callback();
        } else {
            noMapData = true;
            callback();
            $element.closest('.wii-page-card').toggle(false);
        }
    });
}

function initLineChart(element, callback) {
    const $element = $(element);
    $errorContainer.addClass('d-none');

    $.get($element.data(`fetch-url`), getFiltersValue(), function (response) {
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
        });
        data.datasets = Object.values(datasets);
        let chart = new Chart($element, {
            type: 'line',
            data,
            options: {
                maintainAspectRatio: false,
                spanGaps: true,
                scales: {
                    xAxes: [{
                        ticks: {
                            callback: (label) => {
                                if (/\s/.test(label)) {
                                    return label.split(` `);
                                } else{
                                    return label;
                                }
                            }
                        }
                    }]
                }
            }
        });
        $element.closest('.wii-page-card').toggle(sensors.length > 0);
        if (sensors.length === 0) {
            noChartData = true;
            callback();
        }
    });
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
                                        ? { text: group }
                                        : {}),
                                    ...(groupAsLink
                                        ? { href: groupHref }
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
