<?php

namespace App\Controller\Api\Mobile\Reception;

use App\Annotation as Wii;
use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/mobile/reception-line")]
class ReceptionLineController extends AbstractController {
    #[Route("/list/{reception}", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
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
