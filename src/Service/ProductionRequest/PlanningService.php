<?php

namespace App\Service\ProductionRequest;

use App\Controller\FieldModesController;
use App\Controller\Settings\StatusController;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\DaysWorked;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\WorkFreeDay;
use App\Service\FieldModesService;
use App\Service\FormatService;
use App\Service\LanguageService;
use App\Service\SettingsService;
use App\Service\StatusService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;

readonly class PlanningService {

    public const SORTING_TYPE_BY_DATE = "Date";
    public const SORTING_TYPE_BY_STATUS_STATE = "StatusState";

    public const SORTING_TYPES = [
        self::SORTING_TYPE_BY_DATE,
        self::SORTING_TYPE_BY_STATUS_STATE,
    ];

    public const NB_DAYS_ON_PLANNING = 7;


    public function __construct(
        private FormatService   $formatService,
        private StatusService   $statusService,
    ) {}

    public function createCardConfig(array $displayedFieldsConfig, ProductionRequest $productionRequest, array $fieldModes,Language|string $userLanguage, Language|string|null $defaultLanguage = null): array {
        $cardContent = $displayedFieldsConfig;
        $formatService = $this->formatService;

        foreach ($productionRequest->getType()->getFreeFieldManagementRules() as $freeFieldManagementRule) {
            $freeField = $freeFieldManagementRule->getFreeField();
            $fieldName = "free_field_" . $freeField->getId();
            $fieldDisplayConfig = $this->getFieldDisplayConfig($fieldName, $fieldModes);
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
                                         string                 $sortingType,
                                         string                 $statusMode,
                                         array                  $cards,
                                         array                  $options): array {
        if (!in_array($sortingType, self::SORTING_TYPES)) {
            throw new BadRequestHttpException("Invalid sorting type");
        }

        $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);
        $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);

        $daysWorked = $daysWorkedRepository->getLabelWorkedDays();
        $workFreeDays = $workFreeDayRepository->getWorkFreeDaysToDateTime(true);

        if ($sortingType === self::SORTING_TYPE_BY_DATE) {
            $planningColums = Stream::fill(0, $this::NB_DAYS_ON_PLANNING, null)
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
                ->keymap(function (DateTime $day) {
                    return [$day->format('Y-m-d'), $this->formatService->longDate($day, ["short" => true, "year" => false])];
                })
                ->toArray();
        } else if($sortingType === self::SORTING_TYPE_BY_STATUS_STATE) {
            if (!in_array($statusMode, StatusController::MODES)) {
                throw new BadRequestHttpException("Invalid status mode");
            }
            $planningColums = Stream::from($this->statusService->getStatusStatesValues($statusMode))
                ->keymap(static fn(array $status) => [
                    $status['id'],
                    $status['label']
                ]);
        } else {
            throw new BadRequestHttpException("Invalid sorting type");
        }

        if (!empty($planningColums)) {

            $planningColumnsConfig = Stream::from($planningColums)
                ->map( function (string $columnLabel, string $columnId) use ($options, $planningStart, $cards, $daysWorked, $workFreeDays) {
                    $count = count($cards[$columnId] ?? []);
                    $plurialMark = $count > 1 ? 's' : '';

                    $config = [
                        "columnLeftInfo" => $columnLabel,
                        "cardSelector" => $columnId,
                        "columnClasses" => ["forced"],
                        "columnLeftHint" => "<span class='font-weight-bold'>$count demande$plurialMark</span>",
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
        return []; // TODO
    }
}
