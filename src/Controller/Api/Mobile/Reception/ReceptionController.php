<?php

namespace App\Controller\Api\Mobile\Reception;

use App\Annotation as Wii;
use App\Controller\Api\AbstractApiController;
use App\Entity\Reception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/mobile/reception", name: "api_mobile_reception_")]
class ReceptionController extends AbstractApiController
{
    #[Route("/list", methods: ["GET"], condition: "request.isXmlHttpRequest()")]
    #[Wii\RestAuthenticated]
    #[Wii\RestVersionChecked]
    public function list(EntityManagerInterface $entityManager): JsonResponse {
        $receptionRepository = $entityManager
            ->getRepository(Reception::class);

        $reception = $receptionRepository
            ->getMobileReceptions();

        $response = [
            "success" => true,
            "data" => $reception,
        ];

        return new JsonResponse($response);
    }
}
