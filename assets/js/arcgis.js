import AJAX from './ajax';

const COORDINATES_ADDRESS = `https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates`;
const FIND_ROUTE = `https://route.arcgis.com/arcgis/rest/services/World/Route/NAServer/Route_World/solve`;

export async function findCoordinates(token, address) {
    const params = {
        f: `json`,
        token,
        SingleLine: address,
        maxLocations: 1,
    };

    return await AJAX.url(`GET`, COORDINATES_ADDRESS, params).json();
}

export async function findRoute(token, addresses) {
    const coordinates = await Promise.all(addresses.map(address => findCoordinates(token, address)));

    const params = {
        f: `json`,
        token,
        stops: coordinates.map(coordinates => coordinates.candidates[0].location)
            .map(coordinates => `${coordinates.x},${coordinates.y}` )
            .join(`;`),
    };

    const result = await AJAX.url(`GET`, FIND_ROUTE, params).json();
console.log(result);
    return {
        distance: result.directions[0].summary.totalLength * 1.60934,
        time: result.directions[0].summary.totalDriveTime,
    }
}

const f = {
    "features":[
        {
            "geometry":{
                "spatialReference":{
                    "latestWkid":3857,
                    "wkid":102100
                },
                "x":260643.45574337314,
                "y":6250660.789804819
            },
            "symbol":null,
            "attributes":{
                "Match_addr":"Paris, Île-de-France",
                "Addr_type":"Locality",
                "StAddr":"",
                "City":"Paris",
                "Name":"Paris, Île-de-France_#10000"
            },
            "popupTemplate":null
        },
        {
            "geometry":{
                "spatialReference":{
                    "latestWkid":3857,
                    "wkid":102100
                },
                "x":-64684.41651524238,
                "y":5595849.775027521
            },
            "symbol":null,
            "attributes":{
                "Match_addr":"Bordeaux, Gironde, Nouvelle-Aquitaine",
                "Addr_type":"Locality",
                "StAddr":"",
                "City":"Bordeaux",
                "Name":"Bordeaux, Gironde, Nouvelle-Aquitaine_#10001"
            },
            "popupTemplate":null
        }
    ]
};