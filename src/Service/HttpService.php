<?php

namespace App\Service;

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

}
