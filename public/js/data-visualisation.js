function initMap(route = 'map_data_api') {
    if ($('#map').length > 0) {
        const mapPath = Routing.generate(route, true);
        $.get(mapPath, function (response) {

            let map = Leaflet.map('map').setView([44.831598, -0.577096], 13);

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
                            .on('click', function() {
                                this.bounce(1);
                            })
                            .bindPopup(`Capteur : ${sensor} <br> Date et heure : ${label}`);
                        if (iteration === dates.length - 1) {
                            Leaflet
                                .polyline(polyline, {color: 'blue', snakingSpeed: 200})
                                .addTo(map)
                                .snakeIn()
                                .on('snakeend', function() {
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
                    }, 1000 * index);
                });
            });
        })
    } else {
        console.error('Cannot initialize map, no div with the id "map" is present in the dom, use the according template.');
    }
}

function initLineChart(route = 'chart_data_api') {
    let $canvas = $('#chart');
    if ($canvas.length > 0) {
        const chartPath = Routing.generate(route, true);
        $.get(chartPath, function (response) {
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
        })
    } else {
        console.error('Cannot initialize chart, no canvas with the id "chart" is present in the dom, use the according template.');
    }
}
