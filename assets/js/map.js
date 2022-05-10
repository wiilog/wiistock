import Leaflet from "leaflet";
import AJAX from "./ajax";

const FIND_COORDINATES = `https://nominatim.openstreetmap.org/search`;

export class Map {
    id;
    map;
    locations = [];

    static create(id, options = this.DEFAULT_OPTIONS) {
        const map = new Map();
        map.id = id;
        map.map = Leaflet.map(id, options);
        map.map.setView([46.467247, 2.960474], 5);
        Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map.map);

        return map;
    }

    setMarkers(locations, fit = true) {
        for(const location of this.locations) {
            this.removeMarker(location);
        }

        for(const location of locations) {
            this.addMarker(location);
        }

        if(fit) {
            this.fitBounds();
        }
    }

    addMarker(location) {
        const existing = this.locations.find(l => l.latitude === location.latitude && l.longitude === location.longitude);
        if(existing) {
            return;
        }

        const marker = Leaflet.marker([location.latitude, location.longitude]);
        this.map.addLayer(marker);

        if(location.title) {
            marker.bindPopup(location.title);
        }

        location.marker = marker;
        this.locations.push(location);
    }

    removeMarker(location) {
        this.map.removeLayer(location.marker);
        this.locations.splice(this.locations.indexOf(location), 1);
    }

    fitBounds() {
        if(!this.locations.length) {
            this.map.flyTo([46.467247, 2.960474], 5);
        } else {
            const bounds = Leaflet.latLngBounds();
            for(const location of this.locations) {
                bounds.extend(Leaflet.latLng(location.latitude, location.longitude));
            }

            this.map.flyToBounds(bounds, {
                paddingTopLeft: [0, 30],
            });
        }
    }

    reinitialize() {
        document.getElementById(this.id).innerHTML = `<div id="map"></div>`
    }
}

export async function findCoordinates(address) {
    const params = {
        format: `json`,
        q: address,
    };

    return await AJAX.url(`GET`, FIND_COORDINATES, params).json();
}
