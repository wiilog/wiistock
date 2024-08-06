<?php

namespace App\Controller\ProductionRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Controller\FieldModesController;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\WorkFreeDay;
use App\Service\FieldModesService;
use App\Service\FormatService;
use App\Service\LanguageService;
use App\Service\OperationHistoryService;
use App\Service\ProductionRequest\PlanningService;
use App\Service\ProductionRequest\ProductionRequestService;
use App\Service\StatusService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route('/production/planning', name: 'production_request_planning_')]
class PlanningController extends AbstractController {

    #[Route('/index', name: 'index', methods: self::GET)]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST_PLANNING])]
    public function index(EntityManagerInterface   $entityManager,
                          StatusService            $statusService,
                          ProductionRequestService $productionRequestService): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $currentUser = $this->getUser();

        $types = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);

        $fields = $productionRequestService->getVisibleColumnsConfig($entityManager, $currentUser, FieldModesController::PAGE_PRODUCTION_REQUEST_PLANNING);

        return $this->render('production_request/planning/index.html.twig', [
            "types" => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            "statuses" => $statusRepository->findByCategorieName(CategorieStatut::PRODUCTION),
            "fields" => $fields,
            "statusStateValues" => Stream::from($statusService->getStatusStatesValues())
                ->keymap(static fn(array $status) => [
                    $status['id'],
                    $status['label']
                ])
                ->toArray(),
            "token" => $_SERVER["APP_PRODUCTION_REQUEST_PLANNING_TOKEN"] ?? "",
        ]);
    }

    #[Route('/api', name: 'api', options: ['expose' => true], methods: self::GET)]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST_PLANNING], mode: HasPermission::IN_JSON)]
    public function api(EntityManagerInterface   $entityManager,
                        LanguageService          $languageService,
                        PlanningService          $planningService,
                        ProductionRequestService $productionRequestService,
                        FormatService            $formatService,
                        Request                  $request): Response {
        $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $supFilterRepository = $entityManager->getRepository(FiltreSup::class);
        $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);
        $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $fixedFieldRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $external = $request->query->getBoolean("external");

        $user = $this->getUser();
        $defaultLanguage = $languageService->getDefaultLanguage();
        $userLanguage = $user?->getLanguage() ?: $defaultLanguage;

        $daysWorked = $daysWorkedRepository->getLabelWorkedDays();
        $workFreeDays = $workFreeDayRepository->getWorkFreeDaysToDateTime(true);
        $nbDaysOnPlanning = 7;

        $planningStart = $this->getFormatter()->parseDatetime($request->query->get('date'));
        $planningEnd = (clone $planningStart)->modify("+1 week");
        $planningDays = Stream::fill(0, $nbDaysOnPlanning, null)
            ->filterMap(function ($_, int $index) use ($planningStart, $daysWorked, $workFreeDays) {
                $day = (clone $planningStart)->modify("+$index days");
                if (in_array(strtolower($day->format("l")), $daysWorked)
                    && !in_array($day->format("Y-m-d"), $workFreeDays)) {
                    return $day;
                }
                else {
                    return null;
                }
            })
            ->toArray();

        $fieldModes = $user->getFieldModes(FieldModesController::PAGE_PRODUCTION_REQUEST_PLANNING) ?? Utilisateur::DEFAULT_PRODUCTION_REQUEST_PLANNING_FIELDS_MODES;

        if (!empty($planningDays)) {
            $filters = [];
            if(!$external) {
                $filters = Stream::from($supFilterRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PRODUCTION_PLANNING, $user))
                    ->filter(static fn(array $filter) => ($filter["value"] != "" &&  in_array($filter["field"], [
                            FiltreSup::FIELD_REQUEST_NUMBER,
                            FiltreSup::FIELD_MULTIPLE_TYPES,
                            FiltreSup::FIELD_OPERATORS,
                            'statuses-filter',
                        ])))
                    ->toArray();
            }

            $statuses = Stream::from($statusRepository->findByCategorieName(CategorieStatut::PRODUCTION))
                ->filter(static fn(Statut $status) => $status->isDisplayedOnSchedule())
                ->toArray();

            $productionRequests = $productionRequestRepository->findByStatusCodesAndExpectedAt($filters, $statuses, $planningStart, $planningEnd);
            $displayedFieldsConfig = $productionRequestService->getDisplayedFieldsConfig($external, $fieldModes);

            $cards = Stream::from($productionRequests)
                ->keymap(function (ProductionRequest $productionRequest) use ($planningService, $displayedFieldsConfig, $fieldModes, $user, $userLanguage, $entityManager, $productionRequestService, $formatService, $defaultLanguage, $external) {
                    $cardContent = $planningService->createCardConfig($displayedFieldsConfig, $productionRequest, $fieldModes, $userLanguage, $defaultLanguage);
                    return [
                        $productionRequest->getExpectedAt()->format('Y-m-d'),
                        $this->renderView('production_request/planning/card.html.twig', [
                            "productionRequest" => $productionRequest,
                            "color" => $productionRequest->getType()->getColor() ?: Type::DEFAULT_COLOR,
                            "cardContent" => $cardContent ?? [],
                            "inPlanning" => true,
                            "external" => $external,
                        ])
                    ];
                }, true)
                ->toArray();
            $fieldLineCount = $fixedFieldRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_PRODUCTION, FixedFieldEnum::lineCount->name);
            $displayCountLines = $fieldLineCount?->isDisplayedCreate() || $fieldLineCount?->isDisplayedEdit();

            $countLinesByDate = [];
            if ($displayCountLines) {
                Stream::from($productionRequests)
                    ->map(function (ProductionRequest $productionRequest) use (&$countLinesByDate) {
                        $expectedAt = $productionRequest->getExpectedAt()->format('Y-m-d');
                        $countLinesByDate[$expectedAt] = ($countLinesByDate[$expectedAt] ?? 0) + $productionRequest->getLineCount();
                    });
            }

            $formatter = $this->getFormatter();
            $planningColumns = Stream::from($planningDays)
                ->map(static function (DateTime $day) use ($displayCountLines, $countLinesByDate, $planningStart, $cards, $daysWorked, $workFreeDays, $formatter) {
                    $dayStr = $day->format('Y-m-d');
                    $count = count($cards[$dayStr] ?? []);
                    $sProduction = $count > 1 ? 's' : '';

                    return [
                        "label" => $formatter->longDate($day, ["short" => true, "year" => false]),
                        "cardSelector" => $dayStr,
                        "columnClass" => "forced",
                        "columnHint" => "<span class='font-weight-bold'>$count demande$sProduction</span>",
                        "displayCountLines" => $displayCountLines,
                        "countLines" => $countLinesByDate[$dayStr] ?? 0,
                    ];
                })
                ->toArray();
        }

        return $this->json([
            "success" => true,
            "template" => $this->renderView('production_request/planning/content.html.twig', [
                "planningColumns" => $planningColumns ?? [],
                "cards" => $cards ?? [],
            ]),
        ]);
    }

    #[Route("/externe/{token}", name: "external")]
    public function external(string $token): Response {
        if ($token !== $_SERVER["APP_PRODUCTION_REQUEST_PLANNING_TOKEN"]) {
            return $this->redirectToRoute("access_denied");
        }

        return $this->render("production_request/planning/external.html.twig", [
            "token" => $token,
            "firstRefreshDate" => (new DateTime())->format("d/m/Y H:i"),
        ]);
    }

    #[Route("/update-expected-at/{productionRequest}/{date}/{order}", name: "update_expected_at", options: ["expose" => true], methods: self::PUT)]
    #[HasPermission([Menu::PRODUCTION, Action::EDIT_EXPECTED_DATE_FIELD_PRODUCTION_REQUEST])]
    public function updateExpectedAt(ProductionRequest       $productionRequest,
                                     string                  $date,
                                     string                  $order,
                                     EntityManagerInterface  $entityManager,
                                     FormatService           $formatService,
                                     OperationHistoryService $operationHistoryService): Response {

        $date = new DateTime($date);
        $order = json_decode($order);

        $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);

        $productionRequests = Stream::from($order)
            ->map(static function(?int $productionRequestId) use ($productionRequestRepository) {
                if($productionRequestId) {
                    return $productionRequestRepository->find($productionRequestId);
                } else {
                    return null;
                }
            })
            ->toArray();

        $emptyColumn = Stream::from($productionRequests)->filter()->isEmpty();
        $currentExpectedAt = $productionRequest->getExpectedAt();
        $defaultNewExpectedAt = new DateTime("{$date->format("Y-m-d")} {$currentExpectedAt->format("H:i:s")}");
        if($emptyColumn) {
            $newExpectedAt = $defaultNewExpectedAt;
        } else if(isset($productionRequests[0])) {
            $previousProductionRequest = $productionRequests[0];
            $newExpectedAt = $previousProductionRequest->getExpectedAt()->modify("+1 minute");
        } else {
            $nextProductionRequest = $productionRequests[1];

            $productionRequestExpectedAt = $productionRequest->getExpectedAt();
            $nextProductionRequestExpectedAt = $nextProductionRequest->getExpectedAt();
            $productionRequestExpectedAtTimeToSeconds = (
                ((int)$productionRequestExpectedAt->format("H") * 3600)
                + ((int)$productionRequestExpectedAt->format("i") * 60)
                + ((int)$productionRequestExpectedAt->format("s"))
            );

            $nextProductionRequestExpectedAtTimeToSeconds = (
                ((int)$nextProductionRequestExpectedAt->format("H") * 3600)
                + ((int)$nextProductionRequestExpectedAt->format("i") * 60)
                + ((int)$nextProductionRequestExpectedAt->format("s"))
            );

            if($productionRequestExpectedAtTimeToSeconds < $nextProductionRequestExpectedAtTimeToSeconds) {
                $newExpectedAt = $defaultNewExpectedAt;
            } else {
                $newExpectedAt = $nextProductionRequest->getExpectedAt()->modify("-1 minute");
            }
        }

        $productionRequest->setExpectedAt($newExpectedAt);
        $operationHistoryService->persistProductionHistory(
            $entityManager,
            $productionRequest,
            OperationHistoryService::TYPE_REQUEST_EDITED_DETAILS,
            [
                "user" => $this->getUser(),
                "message" => "<br>" . "<strong>" . FixedFieldEnum::expectedAt->value . "</strong> : " . $formatService->datetime($newExpectedAt, "", true) . "<br>",
            ]
        );

        $entityManager->flush();

        return $this->json([
            "success" => true,
        ]);
    }
}
