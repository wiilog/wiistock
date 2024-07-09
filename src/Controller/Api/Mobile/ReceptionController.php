<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Exceptions\FormException;
use App\Repository\StatutRepository;
use App\Service\ArticleDataService;
use App\Service\MouvementStockService;
use App\Service\ReceptionControllerService;
use App\Service\ReceptionService;
use App\Service\TrackingMovementService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;
use DateTime;

#[Route("/api/mobile/receptions")]
class ReceptionController extends AbstractController {

    #[Route(methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
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

    #[Route(methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    #[HasPermission([Menu::NOMADE, Action::MODULE_ACCESS_RECEPTION])]
    public function createReception(EntityManagerInterface      $entityManager,
                                    Request                     $request,
                                    ArticleDataService          $articleDataService,
                                    MouvementStockService       $mouvementStockService,
                                    TrackingMovementService     $trackingMovementService,
                                    ReceptionControllerService  $receptionControllerService): JsonResponse
    {
        $receptionRepository = $entityManager->getRepository(Reception::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $receptionId = $request->request->getInt('receptionId');
        $payload = json_decode($request->request->get('receptionReferenceArticles'), true);

        if (!$payload) {
            throw new FormException("Invalid JSON payload");
        }

        $reception = $receptionRepository->find($receptionId);
        if (!$reception) {
            throw new FormException("La réception n'existe pas");
        }

        $now = new DateTime();
        $receptionLocation = $reception->getLocation();
        $receptionLine = $reception->getLine(null);
        $receptionReferenceArticles = $receptionLine->getReceptionReferenceArticles();

        $receptionControllerService->validateQuantities($payload);

        foreach ($payload as $row) {
            $receptionControllerService->processReceptionRow(
                $reception,
                $receptionReferenceArticles,
                $row,
                $receptionLocation,
                $now,
                $mouvementStockService,
                $trackingMovementService,
                $articleDataService
            );
        }

        $receptionControllerService->updateReceptionStatus($reception, $receptionReferenceArticles, $statusRepository);

        $entityManager->flush();

        return $this->json(['success' => true]);
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
