<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\OperationHistory\ProductionHistoryRecord;
use App\Entity\ProductionRequest;
use App\Entity\StatusHistory;
use App\Service\LanguageService;
use App\Service\OperationHistoryService;
use App\Service\ProductionRequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/production', name: 'production_request_')]
class ProductionRequestController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function index(): Response {
        return $this->render('production_request/index.html.twig', [
            'controller_name' => 'ProductionRequestController',
        ]);
    }

    #[Route("/voir/{id}", name: "show")]
    public function show(EntityManagerInterface   $entityManager,
                         ProductionRequest        $productionRequest,
                         ProductionRequestService $productionRequestService): Response {
        $freeFields = $entityManager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($productionRequest->getType(), CategorieCL::PRODUCTION_REQUEST);

        return $this->render("production_request/show/index.html.twig", [
            "productionRequest" => $productionRequest,
            "detailsConfig" => $productionRequestService->createHeaderDetailsConfig($productionRequest),
            "attachments" => $productionRequest->getAttachments(),
            "freeFields" => $freeFields,
        ]);
    }

    #[Route("/{id}/status-history-api", name: "status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(ProductionRequest $productionRequest,
                                     LanguageService   $languageService): JsonResponse {
        $user = $this->getUser();
        return $this->json([
            "success" => true,
            "template" => $this->renderView('production_request/show/status-history.html.twig', [
                "userLanguage" => $user->getLanguage(),
                "defaultLanguage" => $languageService->getDefaultLanguage(),
                "statusesHistory" => Stream::from($productionRequest->getStatusHistory())
                    ->map(fn(StatusHistory $statusHistory) => [
                        "status" => $this->getFormatter()->status($statusHistory->getStatus()),
                        "date" => $languageService->getCurrentUserLanguageSlug() === Language::FRENCH_SLUG
                            ? $this->getFormatter()->longDate($statusHistory->getDate(), ["short" => true, "time" => true])
                            : $this->getFormatter()->datetime($statusHistory->getDate(), "", false, $user),
                        "user" => $this->getFormatter()->user($statusHistory->getInitiatedBy()),
                    ])
                    ->toArray(),
                "productionRequest" => $productionRequest,
            ]),
        ]);
    }

    #[Route("/{id}/production-history-api", name: "operation_history_api", options: ['expose' => true], methods: "GET")]
    public function productionHistoryApi(ProductionRequest       $productionRequest,
                                         OperationHistoryService $operationHistoryService): JsonResponse {
        return $this->json([
            "success" => true,
            "template" => $this->renderView("production_request/show/production-history.html.twig", [
                "entity" => $productionRequest,
                "history" => Stream::from($productionRequest->getHistory())
                    ->sort(static fn(ProductionHistoryRecord $h1, ProductionHistoryRecord $h2) => (
                        ($h2->getDate() <=> $h1->getDate())
                            ?: ($h2->getId() <=> $h1->getId())
                        )
                    )
                    ->map(static fn(ProductionHistoryRecord $productionHistory) => [
                        "record" => $productionHistory,
                        "icon" => $operationHistoryService->getIconFromType($productionHistory->getRequest(), $productionHistory->getType()),
                    ])
                    ->toArray()
            ]),
        ]);
    }
}
