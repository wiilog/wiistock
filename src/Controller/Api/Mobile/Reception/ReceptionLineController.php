<?php

namespace App\Controller\Api\Mobile\Reception;

use App\Annotation as Wii;
use App\Annotation\HasPermission;
use App\Controller\Api\AbstractApiController;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/mobile/reception-line", name: "api_mobile_reception_line_")]
class ReceptionLineController extends AbstractApiController {
    #[Route("/list/{reception}", name: "list", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestAuthenticated]
    #[Wii\RestVersionChecked]
    #[HasPermission([Menu::NOMADE, Action::MODULE_ACCESS_RECEPTION])]
    public function list(EntityManagerInterface     $entityManager,
                         Reception                  $reception): JsonResponse {
        $receptionLineRepository = $entityManager->getRepository(ReceptionLine::class);
        $lines = $receptionLineRepository->getByReception($reception, []);

        return new JsonResponse([
            "success" => true,
            "data" => $lines,
        ]);
    }
}
