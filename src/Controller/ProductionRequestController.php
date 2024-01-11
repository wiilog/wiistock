<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\CategorieCL;
use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\FixedFieldService;
use App\Service\ProductionRequestService;
use App\Service\StatusService;
use App\Service\TranslationService;
use App\Service\VisibleColumnService;
use App\Service\StatusHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\OperationHistory\ProductionHistoryRecord;
use App\Entity\StatusHistory;
use App\Service\LanguageService;
use App\Service\OperationHistoryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/production', name: 'production_request_')]
class ProductionRequestController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function index(Request $request,
                          EntityManagerInterface   $entityManager,
                          ProductionRequestService $productionRequestService,
                          StatusService          $statusService): Response {
        $fixedFieldRepository = $entityManager->getRepository(FixedFieldStandard::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $fields = $productionRequestService->getVisibleColumnsConfig($entityManager, $currentUser);

        // repository
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $filterSupRepository = $entityManager->getRepository(FiltreSup::class);

        // data from request
        $query = $request->query;
        $typesFilter = $query->has('types') ? $query->all('types', '') : [];
        $statusesFilter = $query->has('statuses') ? $query->all('statuses', '') : [];

        // case type filter selected
        if (!empty($typesFilter)) {
            $typesFilter = Stream::from($typeRepository->findBy(['id' => $typesFilter]))
                ->filterMap(fn(Type $type) => $type->getLabelIn($currentUser->getLanguage()))
                ->toArray();
        }

        // case status filter selected
        if (!empty($statusesFilter)) {
            $statusesFilter = Stream::from($statutRepository->findBy(['id' => $statusesFilter]))
                ->map(fn(Statut $status) => $status->getId())
                ->toArray();
        }

        $types = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);
        $attachmentAssigned = (bool)$filterSupRepository->findOnebyFieldAndPageAndUser("attachmentsAssigned", 'production', $currentUser);

        $dateChoices =
            [
                [
                    'name' => 'createdAd',
                    'label' => 'Date de création',
                ],
                [
                    'name' => 'expectedAt',
                    'label' => 'Date de réalisation',
                ],
            ];

        foreach ($dateChoices as &$choice) {
            $choice['default'] = (bool)$filterSupRepository->findOnebyFieldAndPageAndUser("date-choice_{$choice['name']}", 'production', $currentUser);
        }

        $dateChoicesHasDefault = Stream::from($dateChoices)
            ->some(static fn($choice) => ($choice['default'] ?? false));

        if ($dateChoicesHasDefault) {
            $dateChoices[0]['default'] = true;
        }

        return $this->render('production_request/index.html.twig', [
            "productionRequest" => new ProductionRequest(),
            "fieldsParam" => $fixedFieldRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_PRODUCTION),
            "emergencies" => $fixedFieldRepository->getElements(FixedFieldStandard::ENTITY_CODE_PRODUCTION, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            "fields" => $fields,
            "initial_visible_columns" => $this->apiColumns($productionRequestService, $entityManager)->getContent(),
            "dateChoices" => $dateChoices,
            "types" => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            "statusStateValues" => Stream::from($statusService->getStatusStatesValues())
                ->reduce(function($status, $item) {
                    $status[$item['id']] = $item['label'];
                    return $status;
                }, []),
            "typesFilter" => $typesFilter,
            "statusFilter" => $statusesFilter,
            "statuses" => $statutRepository->findByCategorieName(CategorieStatut::PRODUCTION, 'displayOrder'),
            "attachmentAssigned" => $attachmentAssigned,
        ]);
    }

    #[Route("/api-columns", name: "api_columns", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function apiColumns(ProductionRequestService $service, EntityManagerInterface $entityManager): Response {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $columns = $service->getVisibleColumnsConfig($entityManager, $currentUser);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "api", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function api(Request                  $request,
                        ProductionRequestService $productionRequestService,
                        EntityManagerInterface   $entityManager): Response {
        return $this->json($productionRequestService->getDataForDatatable($entityManager, $request));
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

    #[Route('/new', name: 'new', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PRODUCTION, Action::CREATE_PRODUCTION_REQUEST])]
    public function new(EntityManagerInterface   $entityManager,
                        Request                  $request,
                        ProductionRequestService $productionRequestService,
                        OperationHistoryService  $operationHistoryService,
                        StatusHistoryService     $statusHistoryService,
                        FixedFieldService        $fieldsParamService): JsonResponse {
        $data = $fieldsParamService->checkForErrors($entityManager, $request->request, FixedFieldStandard::ENTITY_CODE_PRODUCTION, true)->all();
        $productionRequest = $productionRequestService->updateProductionRequest($entityManager, new ProductionRequest(), $data, $request->files);
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
            'msg' => "Votre demande de production a bien été créée."
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

    #[Route("/colonne-visible", name: "set_visible_columns", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST], mode: HasPermission::IN_JSON)]
    public function saveColumnVisible(Request                $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService   $visibleColumnService,
                                      TranslationService     $translationService): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $visibleColumnService->setVisibleColumns('productionRequest', $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translationService->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées', false)
        ]);
    }

    #[Route("/planning", name: "planning", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function planning(): Response {
        return $this->render("production_request/planning/index.html.twig");
    }
}
