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

    // coordinates:[[latitude, longitude], ..... ,[latitude, longitude]]
    setLines(coordinates, color = "black") {
        const lines = Leaflet.polyline(coordinates, {color: color});
        this.map.addLayer(lines);
    }

    setMarker(options) {
        const existing = this.locations.find(l => (options.selector && l.selector === options.selector)
            || (l.latitude === options.latitude && l.longitude === options.longitude));
        let estimated = '';
        if (existing) {
            if (!options.deletion) {
                let currentMarkerPopupContent = existing.marker.getPopup().getContent();
                let $currentMarkerPopupContent = $(`<div>${currentMarkerPopupContent}</div>`);
                let $estimated = $currentMarkerPopupContent.find('.estimated');
                if ($estimated.length) {
                    estimated = $estimated.prop('outerHTML')
                }
            }
            this.removeLocation(existing);
        }

        const marker = Leaflet.marker([options.latitude, options.longitude], {icon: locationIcons[options.icon] || locationIcons.greyLocation});

        if (options.onclick){
            marker.on('click', function (){
                options.onclick();
            });
        }

        this.map.addLayer(marker);

        if (options.popUp) {
            const className = options.isFocused ? "leaflet-popup-border" : undefined;
            marker
                .bindPopup(options.popUp += estimated, {
                    closeButton: false,
                    autoClose: false,
                    closeOnClick: false,
                    className
                })
                .openPopup();
        }

        options.marker = marker;
        this.locations.push(options);

        return marker;
    }

    removeLocation(location) {
        this.map.removeLayer(location.marker);
        this.locations.splice(this.locations.indexOf(location), 1);
    }

    estimatePopupMarker(options) {
        const existing = this.locations.find(l => (options.selector && l.selector === options.selector)
            || (l.latitude === options.latitude && l.longitude === options.longitude));
        if (existing) {
            let marker = existing.marker;
            let currentMarkerPopupContent = marker.getPopup().getContent();
            let $currentMarkerPopupContent = $(`<div>${currentMarkerPopupContent}</div>`);
            let $estimated = $currentMarkerPopupContent.find('.estimated');
            if ($estimated.length) {
                $estimated.html(options.estimation);
            } else {
                $currentMarkerPopupContent.append(options.estimation);
            }
            marker.setPopupContent($currentMarkerPopupContent.html());
        }
    }

    removeMarker(marker) {
        if (marker) {
            this.map.removeLayer(marker);
            const {lat, lng} = marker.getLatLng();
            const location = this.locations.find((location) => (location.latitude === lat && location.longitude === lng));
            if (location) {
                this.removeLocation(location);
            }
        }
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

    getMarker(options) {
        return this.locations.find(l => (options.selector && l.selector === options.selector)
            || (l.latitude === options.latitude && l.longitude === options.longitude)).marker ?? null;
    }

    createPopupContent(contactInformation, index, estimation = null) {
        const htmlIndex = index ? `<span class='index'>${index}</span>` : ``;
        const htmlTime = contactInformation.time ? `<span class='time'>${contactInformation.time || ""}</span>` : ``;
        const estimated = estimation ? `<span class="time estimated">Estimé : ' + ${estimation} + '</span>` : ``;
        return `
            ${htmlIndex}
            <span class='contact'>${contactInformation.contact || ""}</span>
            ${htmlTime}
            ${estimated}
    `;
    }
}

export async function findCoordinates(address) {
    const params = {
        format: `json`,
        q: address,
    };

    return await AJAX.url(`GET`, FIND_COORDINATES, params).json();
}
