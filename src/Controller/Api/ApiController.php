<?php

namespace App\Controller\Api;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;


#[Route("/api", name: "api_")]
class ApiController extends AbstractController {

    #[Route("/ping", name: 'ping', options: ["expose" => true], methods: [self::GET])]
    public function ping(): JsonResponse
    {
        dump("OK");
        return $this->json([
            'success' => true,
        ]);
    }
}
