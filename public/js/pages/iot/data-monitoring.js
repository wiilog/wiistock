$(document).ready(() => {
    $(`[data-map]`).each((i, elem) => initMap(elem));
    $(`[data-chart]`).each((i, elem) => initLineChart(elem));

    $(document).arrive(`[data-map]`, elem => initMap(elem));
    $(document).arrive(`[data-chart]`, elem => initLineChart(elem));

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
                $(response.selector).text(response.date);
            }
        });
    }
});

function filter() {
    $(`[data-map]`).each((i, elem) => initMap(elem));
    $(`[data-chart]`).each((i, elem) => initLineChart(elem));
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
function initMap(element) {
    const $element = $(element);

    $.get($element.data(`fetch-url`), getFiltersValue(), function (response) {
        if(previousMap) {
            previousMap.off();
            previousMap.remove();
        }

        let map = Leaflet.map(element).setView([44.831598, -0.577096], 13);
        previousMap = map;

        Leaflet
            .tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}
            )
            .addTo(map);

        let sensors = Object.keys(response);
        let index = 0;

        let globalBounds = Leaflet.latLngBounds();

        const responseValues = Object.values(response);
        // hide the map if there are no sensors
        $element.closest('.wii-page-card').toggle(true);
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
        } else {
            $element.closest('.wii-page-card').toggle(false);
        }
    });
}

function initLineChart(element) {
    const $element = $(element);

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
                let lastTitle;
                const $timeline = timeline.map(({title, titleHref, active, group, groupHref, datePrefix, date}, index) => {
                    const hideTitle = lastTitle === title;
                    lastTitle = title;

                    const lastClass = (isEnd && (timeline.length - 1) === index) ? 'last-timeline-cell' : '';
                    const activeClass = active ? 'timeline-cell-active' : '';
                    const withoutTitleClass = hideTitle ? 'timeline-cell-without-title' : '';
                    const largeTimelineCellClass = !isGrouped ? 'timeline-cell-large' : '';
                    const groupAsLink = (group && groupHref);

                    return $('<div/>', {
                        class: 'timeline-row',
                        html: [
                            isGrouped
                                ? $(!groupAsLink ? '<div/>' : '<a/>', {
                                    class: `timeline-cell timeline-cell-left ${lastClass}`,
                                    ...(!hideTitle && group
                                        ? { text: group }
                                        : {}),
                                    ...(groupAsLink
                                        ? { href: groupHref }
                                        : {})
                                })
                                : undefined,
                            $('<div/>', {
                                class: `timeline-cell timeline-cell-right ${lastClass} ${activeClass} ${withoutTitleClass} ${largeTimelineCellClass}`,
                                html: [
                                    ...(!hideTitle && title
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
                                    `<span class="pairing-date">${date}</span>`
                                ]
                            })
                        ]
                    })
                });
                $timelineContainer.append($timeline);

                if (firstLoading) {
                    $timelineContainer
                        .addClass('py-3')
                        .removeClass('py-5');
                }

                if (!isEnd) {
                    $timelineContainer.append(
                        $('<div/>', {
                            class: 'timeline-row timeline-show-more-button-container justify-content-center pt-4',
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
