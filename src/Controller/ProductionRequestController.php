<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\CategorieCL;
use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Service\FixedFieldService;
use App\Service\ProductionRequestService;
use App\Service\StatusHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\OperationHistory\ProductionHistoryRecord;
use App\Entity\StatusHistory;
use App\Service\LanguageService;
use App\Service\OperationHistoryService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use WiiCommon\Helper\Stream;

#[Route('/production', name: 'production_request_')]
class ProductionRequestController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function index(EntityManagerInterface $entityManager): Response {
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        return $this->render('production_request/index.html.twig', [
            'productionRequest' => new ProductionRequest(),
            'fieldsParam' => $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_PRODUCTION),
            'types' => $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_PRODUCTION, FixedFieldStandard::FIELD_CODE_EMERGENCY),
        ]);
    }

    #[Route('/new', name: 'new', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PRODUCTION, Action::CREATE_PRODUCTION_REQUEST])]
    public function new(EntityManagerInterface   $entityManager,
                        Request                  $request,
                        ProductionRequestService $productionRequestService,
                        OperationHistoryService  $operationHistoryService,
                        StatusHistoryService     $statusHistoryService,
                        FixedFieldService        $fieldsParamService): JsonResponse {
        $data = $fieldsParamService->checkForErrors($entityManager, $request->request, FixedFieldStandard::ENTITY_CODE_PRODUCTION, true)->all();
        $productionRequest = $productionRequestService->updateProductionRequest(
            $entityManager,
            new ProductionRequest(),
            $data,
            $request->files,
        );
        $entityManager->persist($productionRequest);

        $statusHistory = $statusHistoryService->updateStatus(
            $entityManager,
            $productionRequest,
            $productionRequest->getStatus(),
            [
                "forceCreation" => true,
                "setStatus" => false,
                "initiatedBy" => $this->getUser(),
            ]
        );

        $productionRequestHistoryRecord = $operationHistoryService->persistProductionHistory(
            $entityManager,
            $productionRequest,
            OperationHistoryService::TYPE_REQUEST_CREATION,
            [
                "user" => $productionRequest->getCreatedBy(),
                "date" => $productionRequest->getCreatedAt(),
                "statusHistory" => $statusHistory,
            ]
        );
        $entityManager->persist($productionRequestHistoryRecord);

        $entityManager->flush();
        return $this->json([
            'success' => true,
            'msg' => "Votre demande de production à bien été créée."
        ]);
    }

    #[Route("/voir/{id}", name: "show")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
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

    #[Route("/{id}/operation-history-api", name: "operation_history_api", options: ['expose' => true], methods: "GET")]
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
