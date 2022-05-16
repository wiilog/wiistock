<?php

namespace App\Service;

use App\Entity\Transport\TransportOrder;
use App\Exceptions\HttpException;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
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

        $request = $this->httpService->request("GET", $url, [
            "f" => "json",
            "token" => $_SERVER["ARCGIS_API_KEY"],
            "SingleLine" => $address,
            "maxLocations" => 1,
        ]);

        $result = json_decode($request->getContent(), true);
        $coordinates = $result["candidates"][0]["location"] ?? [null, null];
        if($coordinates === null || ($coordinates["x"] ?? null) === null || ($coordinates["y"] ?? null) === null) {
            throw new HttpException("L'adresse n'a pas pu être trouvée");
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
                ])
            ]);
            $result = json_decode($request->getContent(), true);
            foreach ($result['directions'] as $direction) {
                $stopsData['data'][] = [
                    "distance" => round($direction['summary']['totalLength'] * self::MILES_TO_KM, 2),
                    "time" => $this->estimateRoundTime(intval($direction["summary"]["totalTime"]))
                ];
            }
        } catch (\Exception $ignored) {
            $stopsData = [
                'success' => false,
                'msg' => 'Erreur lors de la récupération des informations GPS',
                'data' => []
            ];
        }
        return $stopsData;
    }

}
