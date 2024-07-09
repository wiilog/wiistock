<?php

namespace App\Controller\Api\Mobile;

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
use WiiCommon\Helper\Stream;

#[Route("/api/mobile/receptions")]
class ReceptionController extends AbstractController {

    #[Route("/", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    #[HasPermission([Menu::NOMADE, Action::MODULE_ACCESS_RECEPTION])]
    public function getReceptions(EntityManagerInterface $entityManager): JsonResponse {
        $receptionRepository = $entityManager->getRepository(Reception::class);

        $reception = $receptionRepository->getMobileReceptions();

        $response = [
            "success" => true,
            "data" => Stream::from($reception)
                ->map(static function (array $reception) {
                    $reception["emergency"] = $reception["emergency_articles"] || $reception["emergency_manual"];
                    unset($reception["emergency_articles"]);
                    unset($reception["emergency_manual"]);
                    return $reception;
                })
                ->toArray(),
        ];

        return new JsonResponse($response);
    }

    #[Route("/{reception}/lines", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    #[HasPermission([Menu::NOMADE, Action::MODULE_ACCESS_RECEPTION])]
    public function getLines(EntityManagerInterface $entityManager,
                             Reception              $reception): JsonResponse {
        $receptionLineRepository = $entityManager->getRepository(ReceptionLine::class);
        [
            "total" => $countTotal,
            "data" => $results,
        ] = $receptionLineRepository->getByReception($reception, []);

        return $this->json([
            "total" => $countTotal,
            "success" => true,
            "data" => Stream::from($results)
                ->map(static fn(array $line) => [
                    ...$line,
                    "references" => Stream::from($line["references"])
                        ->map(static fn(array $referenceLine) => [
                            ...$referenceLine,
                            "quantityToReceive" => ($referenceLine["quantityToReceive"] ?: 0) - ($referenceLine["receivedQuantity"] ?: 0),
                            "receivedQuantity" => 0,
                        ])
                        ->filter(static fn(array $line) => $line["quantityToReceive"] > 0)
                        ->values()
                ])
                ->values()
        ]);
    }
}
