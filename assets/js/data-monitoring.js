$(document).ready(() => $(`[data-map]`).each((i, elem) => initMap(elem)))
    .arrive(`[data-map]`, elem => initMap(elem));

$(document).ready(() => $(`[data-chart]`).each((i, elem) => initLineChart(elem)))
    .arrive(`[data-chart]`, elem => initLineChart(elem));

function initMap(element, route = 'map_data_api') {
    $.get(Routing.generate(route, true), function (response) {
        let map = Leaflet.map(element).setView([44.831598, -0.577096], 13);

        Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        let sensors = Object.keys(response);
        let index = 0;

        let globalBounds = Leaflet.latLngBounds();

        Object.values(response).forEach((date => {
            Object.values(date).forEach(coordinates => {
                globalBounds.extend(coordinates)      // Extend LatLngBounds with coordinates
            })
        }))

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
                    if (iteration === dates.length - 1) {
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
    })
}

function initLineChart(element, route = 'chart_data_api') {
    const $canvas = $(element);

    $.get(Routing.generate(route, true), function (response) {
        let data = {
            datasets: [],
            labels: []
        };
        let sensorDates = Object.keys(response).filter((key) => key !== 'colors');
        const sensors = Object.keys(response['colors']);
        let datasets = {};
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
        let chart = new Chart($canvas, {
            type: 'line',
            data,
            options: {
                maintainAspectRatio: false,
                spanGaps: true,
            }
        });
    });
}
