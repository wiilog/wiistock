<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\Attribute\Required;

class HttpService {

    public const GET = "GET";
    public const POST = "POST";
    public const PUT = "PUT";
    public const PATCH = "PATCH";
    public const DELETE = "DELETE";

    #[Required]
    public HttpClientInterface $client;

    public function request(string $method, string $url, array $data = []): ResponseInterface {
        $body = null;
        if ($method === "GET" || $method === "DELETE") {
            $url .= "?" . http_build_query($data);
        }
        else {
            $body = json_encode($data);
        }

        return $this->client->request($method, $url, [
            "body" => $body,
        ]);
    }

    public function fetchCoordinates(string $address): array {
        $url = "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates";

        $request = $this->request("GET", $url, [
            "f" => "json",
            "token" => $_SERVER["ARCGIS_API_KEY"],
            "SingleLine" => $address,
            "maxLocations" => 1,
        ]);

        $result = json_decode($request->getContent(), true);
        $coordinates = $result["candidates"][0]["location"] ?? [null, null];

        return [
            $coordinates["y"], //latitude
            $coordinates["x"], //longitude
        ];
    }

}