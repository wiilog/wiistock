<?php

namespace App\Service;

use App\Entity\Transport\TransportOrder;
use App\Exceptions\GeoException;
use Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class GeoService
{
    public const MILES_TO_KM = 1.60934;

    #[Required]
    public HttpClientInterface $client;

    #[Required]
    public HttpService $httpService;


    public function fetchCoordinates(string $address): array
    {
        $url = "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates";

        if (!isset($_SERVER['ARCGIS_API_KEY'])) {
            throw new GeoException("La configuration de l'instance permettant de récupérer les informations GPS est invalide");
        }

        try {
            $request = $this->httpService->request("GET", $url, [
                "f" => "json",
                "token" => $_SERVER["ARCGIS_API_KEY"],
                "SingleLine" => $address,
                "maxLocations" => 1,
            ]);
        }
        catch (\Throwable) {
            throw new GeoException('Erreur lors de la récupération des informations GPS');
        }

        $result = json_decode($request->getContent(), true);
        $coordinates = $result["candidates"][0]["location"] ?? [null, null];

        if(!isset($coordinates['x']) || !isset($coordinates["y"])) {
            throw new GeoException("L'adresse n'a pas pu être trouvée");
        }

        return [
            $coordinates["y"], //latitude
            $coordinates["x"], //longitude
        ];
    }

    public function getStopsCoordinates(array $transportOrders)
    {
        return Stream::from($transportOrders)
            ->map(function (TransportOrder $order) {
                $contact = $order->getRequest()->getContact();
                return [
                    "longitude" => $contact->getAddressLongitude(),
                    "latitude" => $contact->getAddressLatitude()
                ];
            })
            ->toArray();
    }

    public function estimateRoundTime($time): string
    {
        $hours = intval($time / 60);
        $minutes = $time % 60;

        // returns the time already formatted
        return sprintf('%02d:%02d', $hours, $minutes);
    }

// !! Valeurs renseignées en dur dans l'appel dans RequestController pour le developpement
// http->fetchStopsData([["latitude" =>  48.866667, "longitude" => 2.33333 ],[ "latitude" => 44.837789, "longitude" =>-0.57918 ],[ "latitude" => 45.759060, "longitude" =>4.847331]]);
// tableau a construire en utilisant HttpService::getStopsCoordinates()
    public function fetchStopsData(array $coordinates): array
    {
        if (!isset($_SERVER['ARCGIS_API_KEY'])) {
            throw new GeoException("La configuration de l'instance permettant de récupérer les informations GPS est invalide");
        }

        $stopsData = [
            'success' => true,
            'msg' => 'OK'
        ];
        $url = "https://route.arcgis.com/arcgis/rest/services/World/Route/NAServer/Route_World/solve";
        $stops = [];
        for ($i = 0; $i < count($coordinates) - 1; $i++) {
            $nextIndex = $i+1;
            $stops[] =
                [
                    "geometry" => [
                        "x" => $coordinates[$i]['longitude'],
                        "y" => $coordinates[$i]['latitude'],
                    ],
                    "attributes" => [
                        "Name" => "From",
                        "RouteName" => "Route $i to $nextIndex",
                    ],
                ];
            $stops[] = [
                "geometry" => [
                    "x" => $coordinates[$nextIndex]['longitude'],
                    "y" => $coordinates[$nextIndex]['latitude'],
                ],
                "attributes" => [
                    "Name" => "To",
                    "RouteName" => "Route $i to $nextIndex",
                ],
            ];
        }

        try {
            $request = $this->httpService->request("GET", $url, [
                "f" => "json",
                "token" => $_SERVER["ARCGIS_API_KEY"],
                "returnRoutes" => false,
                "stops" => json_encode([
                    "spatialReference" => [
                        "wkid" => 4326,
                    ],
                    "features" => $stops,
                ]),
            ]);
            $result = json_decode($request->getContent(), true);
            foreach ($result['directions'] as $direction) {
                preg_match("^Route (\d+) to \d+$^", $direction["routeName"], $matches);
                $routeIndex = intval($matches[1]);
                $stopsData['data'][$routeIndex] = [
                    "distance" => round($direction['summary']['totalLength'] * self::MILES_TO_KM, 2),
                    "time" => $this->estimateRoundTime(intval($direction["summary"]["totalTime"])),
                    "end" => $coordinates[$routeIndex+1],
                ];
            }
        } catch (Exception) {
            throw new GeoException('Erreur lors de la récupération des informations GPS');
        }
        return $stopsData;
    }

    // retourne en mètre la distance entre un tableau de point
    public function getDistanceBetween(array $coordinates): float {
        $length = count($coordinates);
        return Stream::from($coordinates)
            ->map(function(array $current, $index) use ($length, $coordinates) {
                if ($index < $length - 1) {
                    $next = $coordinates[$index + 1];
                    return $this->vincentyGreatCircleDistance($current['latitude'], $current['longitude'], $next['latitude'], $next['longitude']);
                }
                return null;
            })
            ->filter()
            ->sum();
    }

    public function directArcgisQuery(array $coordinates) {

        if (!isset($_SERVER['ARCGIS_API_KEY'])) {
            throw new GeoException("La configuration de l'instance permettant de récupérer les informations GPS est invalide");
        }

        $stopsData = [
            'success' => true,
            'msg' => 'OK'
        ];
        $step = ceil(count($coordinates) / 140);
        $coordinates = Stream::from($coordinates)
            ->filter(fn($coordinates, $key) => $coordinates['keep'] ?: ($key % $step === 0))
            ->sort(fn(array $coordinate1, array $coordinate2) => $coordinate1['index'] <=> $coordinate2['index'])
            ->map(fn(array $coordinates) => [
                'geometry' => $coordinates['geometry']
            ])
            ->reindex()
            ->toArray();
        $url = "https://route.arcgis.com/arcgis/rest/services/World/Route/NAServer/Route_World/solve";
        try {
            $request = $this->httpService->request("GET", $url, [
                "f" => "json",
                "token" => $_SERVER["ARCGIS_API_KEY"],
                "returnRoutes" => false,
                "stops" => json_encode([
                    "spatialReference" => [
                        "wkid" => 4326,
                    ],
                    "features" => $coordinates,
                ]),
            ]);
            $result = json_decode($request->getContent(), true);
            $distance = round($result['directions'][0]['summary']['totalLength'] * self::MILES_TO_KM, 2);
            $stopsData['distance'] = $distance;
        } catch (Exception) {
            throw new GeoException('Erreur lors de la récupération des informations GPS');
        }
        return $stopsData;
    }

    // retourne en mètres
    public function vincentyGreatCircleDistance(float $latitudeFrom,
                                                float $longitudeFrom,
                                                float $latitudeTo,
                                                float $longitudeTo,
                                                int $earthRadius = 6371000) {
        // tout plein de trucs de maths compliqués, pas super interessant
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }
}
