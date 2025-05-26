<?php

namespace App\Service\Emergency;


use App\Controller\FieldModesController;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\Emergency\Emergency;
use App\Entity\Emergency\EmergencyTriggerEnum;
use App\Entity\Emergency\EndEmergencyCriteriaEnum;
use App\Entity\Emergency\StockEmergency;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\FreeField\FreeField;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Transporteur;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FieldModesService;
use App\Service\FixedFieldService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\SettingsService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class EmergencyService
{

    private array $__arrival_emergency_fields;

    public function __construct(
        private AttachmentService $attachmentService,
        private FixedFieldService $fieldsParamService,
        private FormatService     $formatService,
        private SettingsService   $settingService,
        private FieldModesService $fieldModesService,
        private UserService       $userService,
        private Twig_Environment  $templating,
        private FreeFieldService  $freeFieldService,
        private RouterInterface   $router,
        private CSVExportService  $csvExportService,
    ) {}

    /**
     * @return array{
     *     types: array{
     *       label?: string,
     *       value?: int,
     *       selected?: boolean,
     *       'category-type'?: string,
     *     },
     *     fieldParams: array<string, array{
     *       requiredCreate?: string,
     *       displayedCreate?: string,
     *       requiredEdit?: string,
     *       displayedEdit?: string,
     *     }>,
     *     emergency: Emergency|null,
     * }
     */
    public function getEmergencyConfig(EntityManagerInterface $entityManager,
                                       Emergency              $emergency = null): array {
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $trackingEmergencyFieldsParam = $fixedFieldByTypeRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_TRACKING_EMERGENCY, [
            FixedFieldByType::ATTRIBUTE_REQUIRED_CREATE,
            FixedFieldByType::ATTRIBUTE_DISPLAYED_CREATE,
            FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT,
            FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT,
        ]);
        $stockEmergencyFieldsParam = $fixedFieldByTypeRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_STOCK_EMERGENCY, [
            FixedFieldByType::ATTRIBUTE_REQUIRED_CREATE,
            FixedFieldByType::ATTRIBUTE_DISPLAYED_CREATE,
            FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT,
            FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT,
        ]);


        /**
         * We associate fieldCode to a fieldParam array for each stock or tracking emergency fixed fields
         * @var array<string, array{
         *     requiredCreate?: string,
         *     displayedCreate?: string,
         *     requiredEdit?: string,
         *     displayedEdit?: string,
         * }> $fieldParams
         */
        $fieldParams = Stream::from(
            Stream::keys($trackingEmergencyFieldsParam),
            Stream::keys($stockEmergencyFieldsParam),
        )
            ->unique()
            // for each unique field code then we generate an array of field param
            // like: buyer, supplier, orderNumber
            ->keymap(static function (string $fieldCode) use ($trackingEmergencyFieldsParam, $stockEmergencyFieldsParam) {
                $trackingFixedFieldParams = $trackingEmergencyFieldsParam[$fieldCode] ?? [];
                $stockFixedFieldParams = $stockEmergencyFieldsParam[$fieldCode] ?? [];

                /**
                 * @var array{
                 *     requiredCreate?: string,
                 *     displayedCreate?: string,
                 *     requiredEdit?: string,
                 *     displayedEdit?: string,
                 * } $currentFieldParams With value list of type ids separated with space char
                 */
                $currentFieldParams = Stream::from(
                    Stream::keys($trackingFixedFieldParams),
                    Stream::keys($stockFixedFieldParams),
                )
                    ->unique()
                    ->keymap(fn(string $key) => [
                        $key,
                        Stream::from(
                            Stream::explode(" ", $trackingFixedFieldParams[$key] ?? '')->filter(),
                            Stream::explode(" ", $stockFixedFieldParams[$key] ?? '')->filter(),
                        )
                            ->unique()
                            ->join(' ')
                    ])
                    ->toArray();

                return [
                    $fieldCode,
                    $currentFieldParams,
                ];
            })
            ->toArray();

        $emergencyTypes = $typeRepository->findByCategoryLabels([CategoryType::TRACKING_EMERGENCY, CategoryType::STOCK_EMERGENCY], null, [
            'onlyActive' => true,
        ]);

        $defaultType = !$emergency
            ? Stream::from($emergencyTypes)->find(static fn(Type $type) => $type->isDefault())
            : null;

        return [
            'emergencyTypes' => $emergencyTypes,
            'defaultType' => $defaultType,
            'fieldParams' => $fieldParams,
            'emergency' => $emergency ?? null,
        ];
    }

    public function updateEmergency(EntityManagerInterface $entityManager,
                                    Emergency              $emergency,
                                    Request                $request): Emergency {
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);

        $type = $typeRepository->find($request->request->get(FixedFieldEnum::type->name));

        $isStockEmergency = $type->getCategory()->getLabel() === CategoryType::STOCK_EMERGENCY;

        $data = $this->fieldsParamService->checkForErrors(
            $entityManager,
            $request->request,
            $isStockEmergency
                ? FixedFieldStandard::ENTITY_CODE_STOCK_EMERGENCY
                : FixedFieldStandard::ENTITY_CODE_TRACKING_EMERGENCY,
            true);

        $emergency
            ->setType($type)
            ->setCreatedAt(new DateTime());

        if ($request->request->getBoolean("isAttachmentForm")) {
            $this->attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $emergency]);
        }

        if ($data->get(FixedFieldEnum::dateStart->name) || $data->get(EndEmergencyCriteriaEnum::MANUAL->value)) {
            $dateStart = $this->formatService->parseDatetime($data->get(FixedFieldEnum::dateStart->name) ?? $data->get(EndEmergencyCriteriaEnum::MANUAL->value));
            $emergency->setDateStart($dateStart);
        }

        if ($data->get(FixedFieldEnum::dateEnd->name)) {
            $dateEnd = $this->formatService->parseDatetime($data->get(FixedFieldEnum::dateEnd->name));
            $emergency->setDateEnd($dateEnd);
        }

        $emergency->setEndEmergencyCriteria($data->get("endEmergencyCriteria")
            ? EndEmergencyCriteriaEnum::from($data->get("endEmergencyCriteria"))
            : EndEmergencyCriteriaEnum::END_DATE);

        if ($data->has(FixedFieldEnum::supplier->name)) {
            $provider = $data->get(FixedFieldEnum::supplier->name)
                ? $supplierRepository->find($data->get(FixedFieldEnum::supplier->name))
                : null;
            $emergency->setSupplier($provider);
        }

        if ($data->has(FixedFieldEnum::orderNumber->name)) {
            $emergency->setOrderNumber($data->get(FixedFieldEnum::orderNumber->name));
        }

        if ($data->has(FixedFieldEnum::carrierTrackingNumber->name)) {
            $emergency->setCarrierTrackingNumber($data->get(FixedFieldEnum::carrierTrackingNumber->name));
        }

        if ($data->has(FixedFieldEnum::carrier->name)) {
            $carrier = $data->get(FixedFieldEnum::carrier->name)
                ? $carrierRepository->find($data->get(FixedFieldEnum::carrier->name))
                : null;
            $emergency->setCarrier($carrier);
        }

        if($emergency instanceof TrackingEmergency) {
            $this->updateTrackingEmergency($entityManager, $emergency, $data);
        } else if ($emergency instanceof StockEmergency) {
            $this->updateStockEmergency($entityManager, $emergency, $data);
        }

        $this->freeFieldService->manageFreeFields($emergency, $data->all(), $entityManager);

        $this->checkForDuplicates($entityManager, $emergency);

        return $emergency;
    }

    private function updateTrackingEmergency(EntityManagerInterface $entityManager,
                                             TrackingEmergency      $emergency,
                                             InputBag               $data): void {
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        if ($data->has(FixedFieldEnum::buyer->name)) {
            $buyer = $data->get(FixedFieldEnum::buyer->name)
                ? $userRepository->find($data->get(FixedFieldEnum::buyer->name))
                : null;
            $emergency->setBuyer($buyer);
        }

        if ($data->has(FixedFieldEnum::postNumber->name)) {
            $emergency->setPostNumber($data->get(FixedFieldEnum::postNumber->name));
        }

        if ($data->has(FixedFieldEnum::internalArticleCode->name)) {
            $emergency->setInternalArticleCode($data->get(FixedFieldEnum::internalArticleCode->name));
        }

        if ($data->has(FixedFieldEnum::supplierArticleCode->name)) {
            $emergency->setSupplierArticleCode($data->get(FixedFieldEnum::supplierArticleCode->name));
        }
    }

    private function updateStockEmergency(EntityManagerInterface $entityManager,
                                          StockEmergency         $emergency,
                                          InputBag               $data): void {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $emergency->setEmergencyTrigger(EmergencyTriggerEnum::from($data->get("emergencyTrigger")));
        if ($data->has(EndEmergencyCriteriaEnum::REMAINING_QUANTITY->value)) {
            $emergency->setExpectedQuantity($data->get(EndEmergencyCriteriaEnum::REMAINING_QUANTITY->value));
        }

        if ($data->has(FixedFieldEnum::reference->name)) {
            $referenceArticle = $data->get(FixedFieldEnum::reference->name)
                ? $referenceArticleRepository->find($data->get(FixedFieldEnum::reference->name))
                : null;

            $emergency->setReferenceArticle($referenceArticle);
        }

        if ($data->has(FixedFieldEnum::expectedEmergencyLocation->name)) {
            $location = $data->get(FixedFieldEnum::expectedEmergencyLocation->name)
                ? $locationRepository->find($data->get(FixedFieldEnum::expectedEmergencyLocation->name))
                : null;

            $emergency->setExpectedLocation($location);
        }

        if ($data->has(FixedFieldEnum::comment->name)) {
            $emergency->setComment($data->get(FixedFieldEnum::comment->name));
        }
    }

    private function checkForDuplicates(EntityManagerInterface $entityManager,
                                        Emergency              $emergency): void {

        if ($emergency instanceof TrackingEmergency) {
            $trackingEmergencyRepository = $entityManager->getRepository(TrackingEmergency::class);

            $duplicateEmergency = $trackingEmergencyRepository->countMatching(
                $emergency,
                $emergency->getDateStart(),
                $emergency->getDateEnd(),
                $emergency->getSupplier(),
                $emergency->getOrderNumber(),
                $emergency->getPostNumber(),
            );

            if ($duplicateEmergency) {
                throw new FormException("Enregistrement impossible : une urgence similaire existe déjà");
            }
        } else if ($emergency instanceof StockEmergency) {
            $stockEmergencyRepository = $entityManager->getRepository(StockEmergency::class);

            $duplicateEmergency = $stockEmergencyRepository->findOneBy([
                'emergencyTrigger' => EmergencyTriggerEnum::REFERENCE,
                'endEmergencyCriteria' => EndEmergencyCriteriaEnum::REMAINING_QUANTITY,
                'referenceArticle' => $emergency->getReferenceArticle(),
                'closedAt' => null,
            ]);

            if ($duplicateEmergency && $duplicateEmergency->getId() !== $emergency->getId()) {
                throw new FormException("Enregistrement impossible : une urgence similaire existe déjà avec la même référence et le même critère de fin d'urgence quantité");
            }
        }
    }

    /**
     * @return array<TrackingEmergency>
     */
    public function matchingEmergencies(EntityManagerInterface $entityManager,
                                        Arrivage               $arrival,
                                        ?string                $orderNumber,
                                        ?string                $postNumber,
                                        bool                   $excludeTriggered = false): array {
        $trackingEmergencyRepository = $entityManager->getRepository(TrackingEmergency::class);

        if (!isset($this->__arrival_emergency_fields)) {
            $arrivalEmergencyFields = $this->settingService->getValue($entityManager, Setting::ARRIVAL_EMERGENCY_TRIGGERING_FIELDS);
            $this->__arrival_emergency_fields = $arrivalEmergencyFields ? explode(',', $arrivalEmergencyFields) : [];
        }

        return $trackingEmergencyRepository->findMatchingEmergencies(
            $arrival,
            $this->__arrival_emergency_fields,
            $orderNumber,
            $postNumber,
            $excludeTriggered,
        );
    }

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager,
                                            ?Utilisateur           $currentUser,
                                            bool $forExport = false): array {

        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $page = FieldModesController::PAGE_EMERGENCY_LIST;

        $freeFields = $freeFieldRepository->findByTypeCategoriesAndFreeFieldCategories(
            [CategorieCL::STOCK_EMERGENCY, CategorieCL::TRACKING_EMERGENCY]
        );

        if (!$forExport && $currentUser) {
            $fieldsModes = $currentUser->getFieldModes($page) ?? Utilisateur::DEFAULT_FIELDS_MODES[$page];
        } else {
            $fieldsModes = [];
        }

        $columns = [
            ...($forExport ? [] : [[
                'title' => null,
                'name' => 'actions',
                'alwaysVisible' => true,
                'orderable' => false,
                'class' => 'noVis'
            ]]),
            FixedFieldEnum::dateStart,
            FixedFieldEnum::dateEnd,
            ['title' => "Date de clôture", 'name' => 'closedAt'],
            ['title' => "Date dernier déclenchement", 'name' => 'lastTriggeredAt'],
            ['title' => "Numéro dernier arrivage ou réception", 'name' => 'lastEntityNumber', 'orderable' => false],
            FixedFieldEnum::createdAt,
            FixedFieldEnum::orderNumber,
            FixedFieldEnum::postNumber,
            FixedFieldEnum::buyer,
            FixedFieldEnum::supplier,
            FixedFieldEnum::carrier,
            FixedFieldEnum::carrierTrackingNumber,
            FixedFieldEnum::type,
            FixedFieldEnum::internalArticleCode,
            FixedFieldEnum::supplierArticleCode,
            FixedFieldEnum::reference,
            ['title' => "Quantité urgence", 'name' => 'stockEmergencyQuantity'],
            ['title' => "Quantité urgence restante", 'name' => 'remainingStockEmergencyQuantity', 'orderable' => true, 'data' => 'remainingStockEmergencyQuantity'],
        ];

        $columns = Stream::from($columns)
            ->map(function (array|FixedFieldEnum $field): array {
                if ($field instanceof FixedFieldEnum) {
                    $field = [
                        "title" => $field->value,
                        "name" => $field->name,
                        "searchable" => in_array($field, [
                            FixedFieldEnum::orderNumber,
                            FixedFieldEnum::postNumber,
                            FixedFieldEnum::buyer,
                            FixedFieldEnum::supplier,
                            FixedFieldEnum::carrier,
                            FixedFieldEnum::carrierTrackingNumber,
                            FixedFieldEnum::type,
                            FixedFieldEnum::type,
                            FixedFieldEnum::internalArticleCode,
                            FixedFieldEnum::supplierArticleCode,
                            FixedFieldEnum::reference,
                        ], true),
                    ];
                }
                return $field;
            })
            ->toArray();

        return $this->fieldModesService->getArrayConfig($columns, $freeFields, $fieldsModes, $forExport);
    }

    public function getDataForDatatable(EntityManagerInterface $entityManager, ParameterBag $request): array {
        $emergencyRepository = $entityManager->getRepository(Emergency::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $referenceArticleIdFilter = $request->getInt('referenceArticleId') ?: null;
        $types = $request->all('types');
        $emergencyStatuses = $request->all('statut');

        $filters = match (true) {
            $referenceArticleIdFilter !== null => [[
                    "field" => "referenceArticle",
                    "value" => $referenceArticleIdFilter
            ]],
            !empty($emergencyStatuses) => [
                [
                    "field" => FiltreSup::FIELD_EMERGENCY_STATUT,
                    "value" => $emergencyStatuses,
                ],
                [
                    "field" => "multipleTypes",
                    "value" => $types,
                ]
            ],
            default => $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_EMERGENCIES, $this->userService->getUser())
        };

        $queryResult = $emergencyRepository->findByParamsAndFilters(
            $request,
            $filters,
            $this->getVisibleColumnsConfig($entityManager, $this->userService->getUser()),
        );

        $freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig(
            $entityManager,
            [CategorieCL::TRACKING_EMERGENCY, CategorieCL::STOCK_EMERGENCY]
        );

         $datum = Stream::from($queryResult["data"])
             ->map(function (array $data) use ($freeFieldsConfig): array {
                 $data["actions"] = $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                     'actions' => [
                         [
                             "title" => "Modifier",
                             "hasRight" => !($data["closedAt"] ?? false) && $this->userService->hasRightFunction(Menu::QUALI, Action::CREATE_EMERGENCY),
                             "actionOnClick" => true,
                             "icon" => "fas fa-pencil-alt",
                             "attributes" => [
                                 "data-id" => $data["id"],
                                 "data-target" => "#modalEditEmergency",
                                 "data-toggle" => "modal",
                             ],
                         ],
                         [
                             "title" => "Cloturer",
                             "hasRight" => !($data["closedAt"] ?? false) && $this->userService->hasRightFunction(Menu::QUALI, Action::CREATE_EMERGENCY),
                             "icon" => "fa-solid fa-ban",
                             "class" => "close-emergency",
                             "attributes" => [
                                 "data-id" => $data["id"],
                             ],
                         ],
                         [
                             "title" => "Aller vers les arrivages",
                             "hasRight" => $data["emergency_category_label"] === CategoryType::TRACKING_EMERGENCY && $this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI),
                             "icon" => "fa fa-up-right-from-square",
                             "href" => $this->router->generate('arrivage_index', ["emergency" => $data["id"]]),
                         ],
                         [
                             "title" => "Aller vers les réceptions",
                             "hasRight" => $data["emergency_category_label"] === CategoryType::STOCK_EMERGENCY && $this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE),
                             "icon" => "fa fa-up-right-from-square",
                             "href" => $this->router->generate('reception_index', ["emergency" => $data["id"]]),
                         ],
                     ],
                 ]);

                $dateFields = [
                    FixedFieldEnum::dateStart->name,
                    FixedFieldEnum::dateEnd->name,
                    "closedAt",
                    "lastTriggeredAt",
                    FixedFieldEnum::createdAt->name,
                ];

                foreach ($dateFields as $field) {
                    $data[$field] = !empty($data[$field])
                        ? $this->formatService->datetime($data[$field])
                        : "";
                }

                $data["remainingStockEmergencyQuantity"] = $data["end_emergency_criteria"] === EndEmergencyCriteriaEnum::REMAINING_QUANTITY
                    ? $data["remainingStockEmergencyQuantity"] ?? 0
                    : null;

                foreach ($freeFieldsConfig as $freeFieldId => $freeField) {
                    $freeFieldName = $this->fieldModesService->getFreeFieldName($freeFieldId);
                    $freeFieldValue = $data["freeFields"][$freeFieldId] ?? "";
                    $data[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField);
                }

                $data["lastEntityNumber"] = $data["lastArrivalNumber"] ?? $data["lastReceptionNumber"] ?? "";

                return $data;
            })
            ->toArray();

        return [
            'data' => $datum,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function closeEmergency(EntityManagerInterface $entityManager, Emergency $emergency): void {
        $now = new DateTime();
        $emergency->setClosedAt($now);

        $entityManager->flush();
    }

    public function getExportFunction(EntityManagerInterface $entityManager,
                                      DateTime               $dateTimeMin,
                                      DateTime               $dateTimeMax): callable {

        $emergencyRepository = $entityManager->getRepository(Emergency::class);
        $emergencies = $emergencyRepository->iterateByDates($dateTimeMin, $dateTimeMax);
        $freeFieldsConfig = $this->freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::TRACKING_EMERGENCY, CategorieCL::STOCK_EMERGENCY]);

        return function ($handle) use ($emergencies, $freeFieldsConfig) {
            foreach ($emergencies as $emergency) {

                $isStockEmergency = $emergency["stockEmergencyQuantity"] !== null;

                $reference = $isStockEmergency
                    ? $emergency[FixedFieldEnum::reference->name]
                    : null;
                $quantity = $isStockEmergency
                    ? $emergency["stockEmergencyQuantity"]
                    : null;
                $remainingQuantity = $isStockEmergency
                    ? $emergency["remainingStockEmergencyQuantity"]
                    : null;

                $freeFieldValues = $emergency["freeFields"];

                $this->csvExportService->putLine($handle, [
                    $this->formatService->datetime($emergency[FixedFieldEnum::dateStart->name] ?? null),
                    $this->formatService->datetime($emergency[FixedFieldEnum::dateEnd->name] ?? null),
                    $this->formatService->datetime($emergency['closedAt'] ?? null),
                    $this->formatService->datetime($emergency['lastTriggeredAt'] ?? null),
                    $emergency['lastArrivalNumber'] ?? $emergency['lastReceptionNumber'] ?? null,
                    $this->formatService->datetime($emergency[FixedFieldEnum::createdAt->name] ?? null),
                    $emergency[FixedFieldEnum::orderNumber->name] ?? null,
                    $emergency[FixedFieldEnum::postNumber->name] ?? null,
                    $emergency[FixedFieldEnum::buyer->name] ?? null,
                    $emergency[FixedFieldEnum::supplier->name] ?? null,
                    $emergency[FixedFieldEnum::carrier->name] ?? null,
                    $emergency[FixedFieldEnum::carrierTrackingNumber->name] ?? null,
                    $emergency[FixedFieldEnum::type->name] ?? null,
                    $emergency[FixedFieldEnum::internalArticleCode->name] ?? null,
                    $emergency[FixedFieldEnum::supplierArticleCode->name] ?? null,
                    $reference,
                    $quantity,
                    $remainingQuantity,
                    ...(Stream::from($freeFieldsConfig['freeFields'])
                        ->map(function (FreeField $freeField, $freeFieldId) use ($freeFieldValues) {
                            $value = $freeFieldValues[$freeFieldId] ?? null;
                            return $value
                                ? $this->formatService->freeField($value, $freeField)
                                : $value;
                        })
                        ->values()),
                ]);
            }
        };
    }
}
