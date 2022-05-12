import Leaflet from "leaflet";
import AJAX from "./ajax";
import {remove} from "leaflet/src/dom/DomUtil";

const FIND_COORDINATES = `https://nominatim.openstreetmap.org/search`;

const LocationIcon = L.Icon.extend({
    options: {
        iconSize: [30, 30],
        iconAnchor: [15, 30],
        popupAnchor: [0,-30]
    }
});

const locationIcons = {
    blackLocation: new LocationIcon({iconUrl: "/svg/location-black.svg"}),
    blueLocation: new LocationIcon({iconUrl: "/svg/location-blue.svg"}),
    greyLocation: new LocationIcon({iconUrl: "/svg/location-grey.svg"})
}

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

    setMarker(options) {
        const existing = this.locations.find(l => l.latitude === options.latitude && l.longitude === options.longitude);
        if (existing) {
           this.removeMarker(existing);
        }

        const marker = Leaflet.marker([options.latitude, options.longitude], {icon: locationIcons[options.icon] || locationIcons.greyLocation});
        this.map.addLayer(marker);

        if (options.popUp) {
            const className = options.isFocused ? "leaflet-popup-border" : undefined;

            marker.bindPopup(options.popUp, {
                closeButton: false,
                autoClose: false,
                closeOnClick: false,
                className
            }).openPopup();
        }

        options.marker = marker;
        this.locations.push(options);
    }

    removeMarker(location) {
        this.map.removeLayer(location.marker);
        this.locations.splice(this.locations.indexOf(location), 1);
    }

    fitBounds() {
        if (!this.locations.length) {
            this.map.flyTo([46.467247, 2.960474], 5);
        } else {
            const bounds = Leaflet.latLngBounds();
            for (const location of this.locations) {
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
