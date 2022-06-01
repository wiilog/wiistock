<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\Attribute\Required;

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
        if ($method === "GET" || $method === "DELETE") {
            return $this->client->request($method, $url, [
                "query" => $data,
            ]);
        } else {
            $body = json_encode($data);
            return $this->client->request($method, $url, [
                "body" => $body,
            ]);
        }
    }

    // Authentification koovea avec la fonction du dessus ne fonctionne pas, alors qu'avec Guzzle exporté depuis postman oui
    public function requestUsingGuzzle(string $uri, string $method, array $body) {
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $request = new Request($method, $uri, $headers, json_encode($body));

        try {
            $request = $client->send($request);
            if ($request->getStatusCode() === 200) {
                return json_decode($request->getBody(), true);
            }
        } catch (\Exception $ignored) {
        }
        return null;
    }

}
