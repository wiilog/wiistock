<?php

namespace App\Service;

use App\Exceptions\HttpException;
use RuntimeException;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use Throwable;

class HttpService
{

    public const GET = "GET";
    public const POST = "POST";
    public const PUT = "PUT";
    public const PATCH = "PATCH";
    public const DELETE = "DELETE";

    #[Required]
    public HttpClientInterface $client;

    public function request(string $method,
                            string $url,
                            array $data = []): ResponseInterface
    {
        $body = null;
        if ($method === "GET" || $method === "DELETE") {
            $url .= "?" . http_build_query($data);
        } else {
            $body = json_encode($data);
        }

            return $this->client->request($method, $url, [
                "body" => $body,
            ]);
        } catch (Throwable $e) {
            throw new HttpException("Une erreur est survenue lors de l'éxecution de la requête", $e->getMessage());
        }
    }

    public function fetchCoordinates(string $address): array
    {
        $url = "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates";

        $request = $this->request("GET", $url, [
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

    public function getStopsCoordinates(TransportRound $transportRound)
    {
        $coordinates = Stream::from($transportRound->getTransportRoundLines())
            ->map(function (TransportRoundLine $line) {
                $contact = $line->getOrder()->getRequest()->getContact();
                return [
                    "longitude" => $contact->getAddressLongitude(),
                    "latitude" => $contact->getAddressLatitude()
                ];
            })
            ->toArray();
        $this->fetchStopsData($coordinates);
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
        $stopsData = [];
        $url = "https://route.arcgis.com/arcgis/rest/services/World/Route/NAServer/Route_World/solve";
        for ($i = 0; $i < count($coordinates) - 1; $i++) {
            $request = $this->request("GET", $url, [
                "f" => "json",
                "token" => $_SERVER["ARCGIS_API_KEY"],
                "returnRoutes" => false,
                "stops" => $coordinates[$i]['longitude'] . ',' . $coordinates[$i]['latitude'] . ';' . $coordinates[$i + 1]['longitude'] . ',' . $coordinates[$i + 1]['latitude']
            ]);
            $result = json_decode($request->getContent(), true);

            $stopsData[] = [
                "distance" => round($result['directions'][0]['summary']['totalLength'] * 1.60934, 2),
                "time" => $this->estimateRoundTime(intval($result['directions'][0]["summary"]["totalTime"]))
            ];
        }

        return $stopsData;
    }

}
