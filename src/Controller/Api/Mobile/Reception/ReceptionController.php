<?php

namespace App\Controller\Api\Mobile\Reception;

use App\Annotation as Wii;
use App\Annotation\HasPermission;
use App\Controller\Api\AbstractApiController;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/mobile/reception", name: "api_mobile_reception_")]
class ReceptionController extends AbstractApiController {
    #[Route("/list", name: "list", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestAuthenticated]
    #[Wii\RestVersionChecked]
    #[HasPermission([Menu::NOMADE, Action::MODULE_ACCESS_RECEPTION])]
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
