<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Controller\Annotations as Rest;

class ApiController extends AbstractApiController {

    /**
     * @Rest\Get("/api/ping")
     * @Rest\View()
     */
    public function ping(): JsonResponse {
        $response = new JsonResponse(['success' => true]);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
        return $response;
    }

}
