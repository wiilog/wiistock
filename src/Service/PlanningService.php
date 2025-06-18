<?php

namespace App\Service;

use App\Controller\Settings\StatusController;
use App\Entity\FreeField\FreeField;
use App\Entity\FreeField\FreeFieldManagementRule;
use App\Entity\Language;
use App\Service\WorkPeriod\WorkPeriodService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use WiiCommon\Helper\Stream;

class PlanningService {

    public const SORTING_TYPE_BY_DATE = "Date";
    public const SORTING_TYPE_BY_STATUS_STATE = "StatusState";

    public const SORTING_TYPES = [
        self::SORTING_TYPE_BY_DATE => "Planning",
        self::SORTING_TYPE_BY_STATUS_STATE => "Modulaire",
    ];

    public const NB_DAYS_ON_PLANNING = 7;

    public function __construct(
        private WorkPeriodService $workPeriodService,
        private FormatService     $formatService,
        private StatusService     $statusService,
        private FreeFieldService  $freeFieldService,
    ) {}

    public function createCardConfig(array $displayedFieldsConfig, mixed $entity, array $fieldModes, Language|string $userLanguage, Language|string|null $defaultLanguage = null): array {
        $cardContent = $displayedFieldsConfig;
        $formatService = $this->formatService;

        /** @var FreeFieldManagementRule $freeFieldManagementRule */
        foreach ($entity->getType()->getFreeFieldManagementRules() as $freeFieldManagementRule) {
            $freeField = $freeFieldManagementRule->getFreeField();
            $fieldName = $this->freeFieldService->getFreeFieldName($freeField->getId());
            $fieldDisplayConfig = $this->getFieldDisplayConfig($fieldName, $fieldModes);
            if ($fieldDisplayConfig) {
                $cardContent[$fieldDisplayConfig]["rows"][] = [
                    "field" => $freeField,
                    "getDetails" => static fn(mixed $entity, FreeField $freeField) => [
                        "label" => $freeField->getLabelIn($userLanguage, $defaultLanguage),
                        "value" => $formatService->freeField($entity->getFreeFieldValue($freeField->getId()), $freeField)
                    ]
                ];
            }
        }

        return Stream::from($cardContent)
            ->map(static function(array $location) use ($entity) {
                return Stream::from($location)
                    ->map(static function(array $type) use ($entity) {
                        return Stream::from($type)
                            ->map(static function(array $fieldConfig) use ($entity) {
                                return $fieldConfig["getDetails"]($entity, $fieldConfig["field"]);
                            })
                            ->toArray();
                    })
                    ->toArray();
            })
            ->toArray();
    }

    public function getFieldDisplayConfig(string $fieldName,
                                                 $fieldModes): ?string {
        if (in_array(FieldModesService::FIELD_MODE_VISIBLE, $fieldModes[$fieldName] ?? [])) {
            $fieldLocation = "header";
        } else if (in_array(FieldModesService::FIELD_MODE_VISIBLE_IN_DROPDOWN, $fieldModes[$fieldName] ?? [])) {
            $fieldLocation = "dropdown";
        } else {
            $fieldLocation = null;
        }
        return $fieldLocation;
    }

    public function createPlanningConfig(EntityManagerInterface $entityManager,
                                         DateTime               $planningStart,
                                         int                    $step,
                                         string                 $sortingType,
                                         string                 $statusMode,
                                         array                  $cards,
                                         array                  $options): array {
        if (!array_key_exists($sortingType, self::SORTING_TYPES)) {
            throw new BadRequestHttpException("Invalid sorting type");
        }

        if ($sortingType === self::SORTING_TYPE_BY_DATE) {
            $planningColumns = Stream::fill(0, $step, null)
                ->filterMap(function ($_, int $index) use ($planningStart, $entityManager) {
                    $day = (clone $planningStart)->modify("+$index days");
                    if ($this->workPeriodService->isOnWorkPeriod($entityManager, $day, ["onlyDayCheck" => true])) {
                        return $day;
                    }
                    else {
                        return null;
                    }
                })
                ->keymap(function (DateTime $day) {
                    return [
                        $day->format('Y-m-d'),
                        $this->formatService->longDate($day, ["short" => true, "year" => false])
                    ];
                })
                ->toArray();
        } else if($sortingType === self::SORTING_TYPE_BY_STATUS_STATE) {
            if (!in_array($statusMode, StatusController::MODES)) {
                throw new BadRequestHttpException("Invalid status mode");
            }
            $planningColumns = Stream::from($this->statusService->getStatusStatesValues($statusMode))
                ->sort(static fn($firstStatusState, $secondStatusState) => $firstStatusState['id'] - $secondStatusState['id'])
                ->keymap(static fn(array $status) => [
                    $status['id'],
                    $status['label']
                ])
                ->toArray();
        } else {
            throw new BadRequestHttpException("Invalid sorting type");
        }

        if (!empty($planningColumns)) {

            $planningColumnsConfig = Stream::from($planningColumns)
                ->map(function (string $columnLabel, string $columnId) use ($options, $planningStart, $cards) {
                    $count = count($cards[$columnId] ?? []);
                    $pluralMark = $count > 1 ? 's' : '';

                    $config = [
                        "columnLeftInfo" => $columnLabel,
                        "cardSelector" => $columnId,
                        "columnClasses" => ["forced"],
                        "columnLeftHint" => "<span class='font-weight-bold'>$count demande$pluralMark</span>",
                        "countLines" => $countLinesByDate[$columnId] ?? 0,

                    ];

                    if (isset($options["columnRightHints"])) {
                        $config["columnRightHint"] = $options["columnRightHints"][$columnId] ?? null;
                    }

                    if (isset($options["columnRightInfos"])) {
                        $config["columnRightInfo"] = $options["columnRightInfos"][$columnId] ?? null;
                    }

                    return $config;
                })
                ->toArray();

            return [
                "planningColumns" => $planningColumnsConfig,
                "cards" => $cards,
            ];
        }
        return [];
    }
}
