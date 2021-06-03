function initMap(route = 'map_data_api') {
    const mapPath = Routing.generate(route, true);
    $.get(mapPath, function(response) {
        let fakeMarkersAndLabels = response;

        let map = Leaflet.map('map').setView([44.831598, -0.577096], 13);

        Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        let polylines = [];
        let makersKeys = Object.keys(fakeMarkersAndLabels);
        makersKeys.forEach((key) => {
            const coordinatesKey = Object.keys(fakeMarkersAndLabels[key]);
            coordinatesKey.forEach((label) => {
                let polylineIndex = polylines[makersKeys.indexOf(key)]
                const coordinates = fakeMarkersAndLabels[key][label];
                if (!polylineIndex) {
                    polylineIndex = [];
                }
                polylineIndex.push(coordinates);
                polylines[makersKeys.indexOf(key)] = polylineIndex;
                Leaflet.marker(coordinates).addTo(map)
                    .bindPopup(label);
            });
        });
        const multiPolylines = Leaflet.polyline(polylines, {color: 'blue'}).addTo(map);
        map.fitBounds(multiPolylines.getBounds());
    })
}
