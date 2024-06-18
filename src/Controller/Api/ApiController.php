<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api", name: "api_")]
class ApiController extends AbstractApiController {

    #[Route("/ping", name: 'ping', options: ["expose" => true], methods: ['GET'])]
    public function ping(): JsonResponse {
        $response = new JsonResponse(['success' => true]);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
        return $response;
    }
}
