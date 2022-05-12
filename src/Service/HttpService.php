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

    public const MILES_TO_KM = 1.60934;

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
