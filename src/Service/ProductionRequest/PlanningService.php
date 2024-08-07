<?php

namespace App\Service\ProductionRequest;

use App\Controller\FieldModesController;
use App\Entity\CategorieStatut;
use App\Entity\DaysWorked;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Language;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\WorkFreeDay;
use App\Service\FieldModesService;
use App\Service\FormatService;
use App\Service\LanguageService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use WiiCommon\Helper\Stream;

readonly class PlanningService {
    public function __construct(
        private ProductionRequestService $productionRequestService,
        private FormatService                     $formatService,
        private LanguageService          $languageService,
        private Environment        $templating,
    ) {}

    public function createCardConfig(array $displayedFieldsConfig, ProductionRequest $productionRequest, array $fieldModes,Language|string $userLanguage, Language|string|null $defaultLanguage): array {
        $cardContent = $displayedFieldsConfig;
        $formatService = $this->formatService;

        foreach ($productionRequest->getType()->getFreeFieldManagementRules() as $freeFieldManagementRule) {
            $freeField = $freeFieldManagementRule->getFreeField();
            $fieldName = "free_field_" . $freeField->getId();
            $fieldDisplayConfig = $this->productionRequestService->getFieldDisplayConfig($fieldName, $fieldModes);
            if ($fieldDisplayConfig) {
                $cardContent[$fieldDisplayConfig]["rows"][] = [
                    "field" => $freeField,
                    "getDetails" => static fn(ProductionRequest $productionRequest, FreeField $freeField) => [
                        "label" => $freeField->getLabelIn($userLanguage, $defaultLanguage),
                        "value" => $formatService->freeField($productionRequest->getFreeFieldValue($freeField->getId()), $freeField)
                    ]
                ];
            }
        }

        return Stream::from($cardContent)
            ->map(static function(array $location) use ($productionRequest) {
                return Stream::from($location)
                    ->map(static function(array $type) use ($productionRequest) {
                        return Stream::from($type)
                            ->map(static function(array $fieldConfig) use ($productionRequest) {
                                return $fieldConfig["getDetails"]($productionRequest, $fieldConfig["field"]);
                            })
                            ->toArray();
                    })
                    ->toArray();
            })
            ->toArray();
    }

    public function createPlanningConfig(EntityManagerInterface $entityManager,
                                         Request                $request,
                                         ?Utilisateur           $user,
                                         bool                   $external): array {
        $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $supFilterRepository = $entityManager->getRepository(FiltreSup::class);
        $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);
        $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);

        $defaultLanguage = $this->languageService->getDefaultLanguage();
        $userLanguage = $user?->getLanguage() ?: $defaultLanguage;

        $daysWorked = $daysWorkedRepository->getLabelWorkedDays();
        $workFreeDays = $workFreeDayRepository->getWorkFreeDaysToDateTime(true);
        $nbDaysOnPlanning = 7;

        $planningStart = $this->formatService->parseDatetime($request->query->get('date'));
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

        $fieldModes = $user?->getFieldModes(FieldModesController::PAGE_PRODUCTION_REQUEST_PLANNING) ?? Utilisateur::DEFAULT_PRODUCTION_REQUEST_PLANNING_FIELDS_MODES;

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
            $displayedFieldsConfig = $this->productionRequestService->getDisplayedFieldsConfig($external, $fieldModes);

            $cards = Stream::from($productionRequests)
                ->keymap(function (ProductionRequest $productionRequest) use ($displayedFieldsConfig, $fieldModes, $user, $userLanguage, $entityManager, $defaultLanguage, $external) {
                    $cardContent = $this->createCardConfig($displayedFieldsConfig, $productionRequest, $fieldModes, $userLanguage, $defaultLanguage);
                    return [
                        $productionRequest->getExpectedAt()->format('Y-m-d'),
                        $this->templating->render('production_request/planning/card.html.twig', [
                            "productionRequest" => $productionRequest,
                            "color" => $productionRequest->getType()->getColor() ?: Type::DEFAULT_COLOR,
                            "cardContent" => $cardContent,
                            "inPlanning" => true,
                            "external" => $external,
                        ])
                    ];
                }, true)
                ->toArray();

            $displayCountLines = in_array(FieldModesService::FIELD_MODE_VISIBLE_IN_DROPDOWN, $fieldModes[FixedFieldEnum::lineCount->name] ?? [])
                || in_array(FieldModesService::FIELD_MODE_VISIBLE, $fieldModes[FixedFieldEnum::lineCount->name] ?? []);

            $countLinesByDate = [];
            if ($displayCountLines) {
                Stream::from($productionRequests)
                    ->map(function (ProductionRequest $productionRequest) use (&$countLinesByDate) {
                        $expectedAt = $productionRequest->getExpectedAt()->format('Y-m-d');
                        $countLinesByDate[$expectedAt] = ($countLinesByDate[$expectedAt] ?? 0) + $productionRequest->getLineCount();
                    });
            }

            $planningColumns = Stream::from($planningDays)
                ->map( function (DateTime $day) use ($displayCountLines, $countLinesByDate, $planningStart, $cards, $daysWorked, $workFreeDays) {
                    $dayStr = $day->format('Y-m-d');
                    $count = count($cards[$dayStr] ?? []);
                    $sProduction = $count > 1 ? 's' : '';

                    return [
                        "label" => $this->formatService->longDate($day, ["short" => true, "year" => false]),
                        "cardSelector" => $dayStr,
                        "columnClass" => "forced",
                        "columnHint" => "<span class='font-weight-bold'>$count demande$sProduction</span>",
                        "displayCountLines" => $displayCountLines,
                        "countLines" => $countLinesByDate[$dayStr] ?? 0,
                    ];
                })
                ->toArray();

            return [
                "planningColumns" => $planningColumns,
                "cards" => $cards,
            ];
        }


    }
}
