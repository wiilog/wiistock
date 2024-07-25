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
use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\WorkFreeDay;
use App\Repository\ProductionRequestRepository;
use App\Service\FieldModesService;
use App\Service\FormatService;
use App\Service\LanguageService;
use App\Service\OperationHistoryService;
use App\Service\ProductionRequestService;
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
    public function index(EntityManagerInterface $entityManager, StatusService $statusService, ProductionRequestService $productionRequestService): Response {
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
                        ProductionRequestService $productionRequestService,
                        FormatService            $formatService,
                        Request                  $request): Response {
        $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $supFilterRepository = $entityManager->getRepository(FiltreSup::class);
        $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);
        $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);
        $fixedFieldRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

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

            $fieldModes = $user->getFieldModesByPage()[FieldModesController::PAGE_PRODUCTION_REQUEST_PLANNING] ?? Utilisateur::DEFAULT_PRODUCTION_REQUEST_PLANNING_FIELDS_MODES;

            $cards = Stream::from($productionRequests)
                ->keymap(function (ProductionRequest $productionRequest) use ($fieldModes, $user, $entityManager, $productionRequestService, $formatService, $fixedFieldRepository, $freeFieldRepository, $defaultLanguage, $external) {
                    $fields = [
                        [
                            "field" => FixedFieldEnum::status,
                            "type" => "tags",
                            "getDetails" => function(ProductionRequest $productionRequest) use ($formatService, $external, $productionRequestService) {
                                return [
                                    "class" => !$external && $productionRequestService->hasRigthToUpdateStatus($productionRequest) ? "prevent-default open-modal-update-production-request-status" : "",
                                    "color" => $productionRequest->getStatus()->getColor(),
                                    "label" => $formatService->status($productionRequest->getStatus()),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::productArticleCode,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                return [
                                    "label" => $field->value,
                                    "value" => $productionRequest->getProductArticleCode(),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::manufacturingOrderNumber,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                return [
                                    "label" => $field->value,
                                    "value" => $productionRequest->getManufacturingOrderNumber(),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::dropLocation,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) use ($formatService) {
                                return [
                                    "label" => $field->value,
                                    "value" => $formatService->location($productionRequest->getDropLocation()),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::quantity,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                return [
                                    "label" => $field->value,
                                    "value" =>$productionRequest->getQuantity(),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::emergency,
                            "type" => "icons",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                $emergency = $productionRequest->getEmergency();

                                return $emergency
                                    ? [
                                        "path" => "svg/urgence.svg",
                                        "alt" => "icon $field->value",
                                        "title" => "Une urgence est en cours sur cette demande : $emergency",
                                    ]
                                    : null;
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::comment,
                            "type" => "icons",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                $comment = strip_tags($productionRequest->getComment());
                                return $comment
                                    ? [
                                        "path" => "svg/comment-dots-regular.svg",
                                        "alt" => "icon $field->value",
                                        "title" => "Un commentaire est présent sur cette demande : $comment",
                                    ]
                                    : null;
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::lineCount,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                return [
                                    "label" => $field->value,
                                    "value" =>$productionRequest->getLineCount(),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::projectNumber,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                return [
                                    "label" => $field->value,
                                    "value" =>$productionRequest->getProjectNumber(),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::attachments,
                            "type" => "icons",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                $attachmentsCount = $productionRequest->getAttachments()->count();
                                return $attachmentsCount
                                    ? [
                                        "path" => "svg/paperclip.svg",
                                        "alt" => "icon $field->value",
                                        "title" => "$attachmentsCount pièce(s) jointe(s) est/sont présente(s) sur cette demande",
                                    ]
                                    : null;
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::createdBy,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) use ($formatService) {
                                return [
                                    "label" => $field->value,
                                    "value" => $formatService->user($productionRequest->getCreatedBy()),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::type,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) use ($formatService) {
                                return [
                                    "label" => $field->value,
                                    "value" => $formatService->type($productionRequest->getType()),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::treatedBy,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) use ($formatService) {
                                return [
                                    "label" => $field->value,
                                    "value" => $formatService->user($productionRequest->getTreatedBy()),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::expectedAt,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) use ($formatService) {
                                return [
                                    "label" => $field->value,
                                    "value" => $formatService->datetime($productionRequest->getExpectedAt()),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::createdAt,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) use ($formatService) {
                                return [
                                    "label" => $field->value,
                                    "value" => $formatService->datetime($productionRequest->getCreatedAt()),
                                ];
                            },
                        ],
                        [
                            "field" => FixedFieldEnum::number,
                            "type" => "rows",
                            "getDetails" => function(ProductionRequest $productionRequest, FixedFieldEnum $field) {
                                return [
                                    "label" => $field->value,
                                    "value" => $productionRequest->getNumber(),
                                ];
                            },
                        ],
                    ];

                    foreach ($fields as $fieldData) {
                        $field = $fieldData["field"];
                        $getDetails = $fieldData["getDetails"];
                        if (in_array(FieldModesService::FIELD_MODE_VISIBLE, $fieldModes[$field->name] ?? [])) {
                            $fieldLocation = "header";
                        } else if (in_array(FieldModesService::FIELD_MODE_VISIBLE_IN_DROPDOWN, $fieldModes[$field->name] ?? [])) {
                            $fieldLocation = "dropdown";
                        } else {
                            $fieldLocation = null;
                        }
                        if($fieldLocation) {
                            $cardContent[$fieldLocation][$fieldData["type"]][] = $getDetails($productionRequest, $field);
                        }
                    }

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

            $countLinesByDate = [];

            Stream::from($productionRequests)
                ->map(function (ProductionRequest $productionRequest) use (&$countLinesByDate) {
                    $expectedAt = $productionRequest->getExpectedAt()->format('Y-m-d');
                    $countLinesByDate[$expectedAt] = ($countLinesByDate[$expectedAt] ?? 0) + $productionRequest->getLineCount();
            });

            $planningColumns = Stream::from($planningDays)
                ->map(function (DateTime $day) use ($countLinesByDate, $planningStart, $cards, $daysWorked, $workFreeDays) {
                    $dayStr = $day->format('Y-m-d');
                    $count = count($cards[$dayStr] ?? []);
                    $sProduction = $count > 1 ? 's' : '';

                    return [
                        "label" => $this->getFormatter()->longDate($day, ["short" => true, "year" => false]),
                        "cardSelector" => $dayStr,
                        "columnClass" => "forced",
                        "columnHint" => "<span class='font-weight-bold'>$count demande$sProduction</span>",
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
