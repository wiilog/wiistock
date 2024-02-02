<?php

namespace App\Controller\ProductionRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\WorkFreeDay;
use App\Service\FormatService;
use App\Service\LanguageService;
use App\Service\OperationHistoryService;
use App\Service\StatusService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/production/planning', name: 'production_request_planning_')]
class PlanningController extends AbstractController {

    #[Route('/index', name: 'index', methods: self::GET)]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST_PLANNING])]
    public function index(EntityManagerInterface $entityManager, StatusService $statusService): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);

        return $this->render('production_request/planning/index.html.twig', [
            "types" => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            "statuses" => $statusRepository->findByCategorieName(CategorieStatut::PRODUCTION),
            "statusStateValues" => Stream::from($statusService->getStatusStatesValues())
                ->keymap(static fn(array $status) => [
                    $status['id'],
                    $status['label']
                ])
                ->toArray(),
        ]);
    }

    #[Route('/api', name: 'api', options: ['expose' => true], methods: self::GET)]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST_PLANNING], mode: HasPermission::IN_JSON)]
    public function api(EntityManagerInterface $entityManager,
                        LanguageService        $languageService,
                        Request                $request): Response {
        $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $supFilterRepository = $entityManager->getRepository(FiltreSup::class);
        $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);
        $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);
        $fixedFieldRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

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

        if (!empty($planningDays)) {
            $filters = $supFilterRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PRODUCTION_PLANNING, $this->getUser());

            $statuses = Stream::from($statusRepository->findByCategorieName(CategorieStatut::PRODUCTION))
                ->filter(static fn(Statut $status) => $status->isDisplayedOnSchedule())
                ->toArray();

            $filters = Stream::from($filters)
                ->filter(static fn(array $filter) => (
                    $filter["field"] === FiltreSup::FIELD_REQUEST_NUMBER
                    || $filter["field"] === FiltreSup::FIELD_TYPE
                    || $filter["field"] === FiltreSup::FIELD_OPERATORS
                    || $filter["field"] === FiltreSup::FIELD_STATUT
                ))
                ->toArray();

            $productionRequests = $productionRequestRepository->findByStatusCodesAndExpectedAt($filters, $statuses, $planningStart, $planningEnd);

            $allTypes = Stream::from($productionRequests)
                ->keymap(fn(ProductionRequest $productionRequest) => [
                    $productionRequest->getType()->getId(),
                    $productionRequest->getType()
                ])
                ->values();
            $freeFieldsByType = $allTypes
                ? Stream::from($freeFieldRepository->findByTypeAndCategorieCLLabel($allTypes, CategorieCL::PRODUCTION_REQUEST))
                    ->keymap(static fn(FreeField $freeField) => [
                        $freeField->getType()->getId(),
                        $freeField
                    ], true)
                    ->toArray()
                : [];
            $fixedFields = Stream::from($fixedFieldRepository->findByEntityCode(FixedFieldStandard::ENTITY_CODE_PRODUCTION, [
                FixedFieldEnum::lineCount->name,
                FixedFieldEnum::projectNumber->name,
                FixedFieldEnum::comment->name,
                FixedFieldEnum::attachments->name
            ]))
                ->keymap(static fn(FixedField $fixedField) => [
                    $fixedField->getFieldCode(),
                    $fixedField
                ])
                ->toArray();

            $cards = Stream::from($productionRequests)
                ->keymap(function (ProductionRequest $productionRequest) use ($fixedFieldRepository, $freeFieldRepository, $userLanguage, $defaultLanguage, $freeFieldsByType, $fixedFields) {
                    $fields = Stream::from([
                        FixedFieldEnum::lineCount->name => $productionRequest->getLineCount(),
                        FixedFieldEnum::projectNumber->name => $productionRequest->getProjectNumber(),
                        FixedFieldEnum::comment->name => $this->getFormatter()->html($productionRequest->getComment()),
                        FixedFieldEnum::attachments->name => $this->getFormatter()->bool(!$productionRequest->getAttachments()->isEmpty()),
                    ])
                        ->filter(static function (mixed $_, string $fieldCode) use ($fixedFieldRepository, $fixedFields) {
                            $fixedField = $fixedFields[$fieldCode] ?? null;
                            return $fixedField->isDisplayedCreate() || $fixedField->isDisplayedEdit();
                        })
                        ->keymap(static fn(mixed $value, string $field) => [
                            FixedFieldEnum::fromCase($field) ?: $field,
                            $value
                        ])
                        ->concat( // concat fixedField with freeField
                            Stream::from($freeFieldsByType[$productionRequest->getType()->getId()] ?? [])
                                ->keymap(static fn(FreeField $freeField) => [
                                    $freeField->getLabelIn($userLanguage, $defaultLanguage),
                                    $productionRequest->getFreeFieldValue($freeField->getId())
                                ])
                        )
                        // remove element without values
                        ->filter(static fn(mixed $value) => !in_array($value, [null, ""]))
                        ->toArray();

                    return [
                        $productionRequest->getExpectedAt()->format('Y-m-d'),
                        $this->renderView('production_request/planning/card.html.twig', [
                            "productionRequest" => $productionRequest,
                            "color" => $productionRequest->getType()->getColor() ?: Type::DEFAULT_COLOR,
                            "inPlanning" => true,
                            "fields" => $fields,
                        ])
                    ];
                }, true)
                ->toArray();

            $planningColumns = Stream::from($planningDays)
                ->map(function (DateTime $day) use ($planningStart, $cards, $daysWorked, $workFreeDays) {
                    $dayStr = $day->format('Y-m-d');
                    $count = count($cards[$dayStr] ?? []);
                    $sProduction = $count > 1 ? 's' : '';

                    return [
                        "label" => $this->getFormatter()->longDate($day, ["short" => true, "year" => false]),
                        "cardSelector" => $dayStr,
                        "columnClass" => "forced",
                        "columnHint" => "<span class='font-weight-bold'>$count demande$sProduction</span>",
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

    #[Route("/external", name: "external")]
    public function external(): Response {
        return $this->render("production_request/planning/external.html.twig");
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
