<?php


namespace App\Service\Tracking;

use App\Controller\FieldModesController;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\IOT\Sensor;
use App\Entity\Language;
use App\Entity\LocationGroup;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\OperationHistory\LogisticUnitHistoryRecord;
use App\Entity\Project;
use App\Entity\ReceiptAssociation;
use App\Entity\Reception;
use App\Entity\Setting;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Type\CategoryType;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\LanguageHelper;
use App\Service\ArrivageService;
use App\Service\CSVExportService;
use App\Service\Dashboard\DashboardService;
use App\Service\DateTimeService;
use App\Service\FieldModesService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\LanguageService;
use App\Service\MailerService;
use App\Service\ProjectHistoryRecordService;
use App\Service\ReceptionLineService;
use App\Service\SettingsService;
use App\Service\TranslationService;
use App\Service\TruckArrivalService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Iterator;
use RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class PackService {

    private const PACK_DEFAULT_TRACKING_DELAY_COLOR = "#000000";

    private array $cache = [];

    public function __construct(
        private CSVExportService            $CSVExportService,
        private RouterInterface             $router,
        private UserService                 $userService,
        private TruckArrivalService         $truckArrivalService,
        private ProjectHistoryRecordService $projectHistoryRecordService,
        private TranslationService          $translationService,
        private ArrivageService             $arrivageService,
        private SettingsService             $settingsService,
        private DateTimeService             $dateTimeService,
        private ReceptionLineService        $receptionLineService,
        private FormatService               $formatService,
        private FieldModesService           $fieldModesService,
        private LanguageService             $languageService,
        private MailerService               $mailerService,
        private TrackingMovementService     $trackingMovementService,
        private Twig_Environment            $templating,
        private EntityManagerInterface      $entityManager,
        private TrackingDelayService        $trackingDelayService,
        private FreeFieldService            $freeFieldService,
    ) {}

    public function getDataForDatatable($params = null): array {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $packRepository = $this->entityManager->getRepository(Pack::class);
        $currentUser = $this->userService->getUser();

        $filters = [];
        $fromDashboard = $params->getBoolean("fromDashboard");
        $naturesFilter = $params->all("natures");
        $locationsFilter = $params->all("lastLocation");
        $isPackWithTracking = $params->getBoolean("isPackWithTracking");
        $trackingDelayEvent = $params->get("trackingDelayEvent");
        if($fromDashboard) {
            $filters = [
                ...($naturesFilter ? [["field" => "natures", "value" => $naturesFilter]] : []),
                ...($locationsFilter ? [["field" => "lastLocation", "value" => $locationsFilter]] : []),
                ...($isPackWithTracking ? [["field" => FiltreSup::FIELD_PACK_WITH_TRACKING, "value" => $isPackWithTracking]] : []),
                ...($trackingDelayEvent ? [["field" => "trackingEventTypes", "value" => DashboardService::TRACKING_EVENT_TO_TREATMENT_DELAY_TYPE[$trackingDelayEvent]]] : []),
            ];
        } else {
            $filters = $params->get("codeUl")
                ? [["field"=> "UL", "value"=> $params->get("codeUl")]]
                : $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $currentUser);
        }

        $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $currentUser->getLanguage() ?: $defaultLanguage;

        $queryResult = $packRepository->findByParamsAndFilters($params, $filters, [
            'defaultLanguage' => $defaultLanguage,
            'language' => $language,
            'fields' => $this->getPackListColumnVisibleConfig($currentUser, $this->entityManager),
        ]);

        $packs = $queryResult["data"];
        $rows = [];
        foreach ($packs as $pack) {
            $rows[] = $this->dataRowPack($pack);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult['count'],
            "recordsTotal" => $queryResult['total'],
        ];
    }

    public function generateTrackingHistoryHtml(LogisticUnitHistoryRecord $logisticUnitHistoryRecord,
                                                int                       $firstRecord,
                                                int                       $latestRecordId): string {
        $user = $this->userService->getUser();
        return $this->templating->render('pack/tracking_history.html.twig', [
            "lastRecordId" => $latestRecordId,
            "firstRecord" => $firstRecord,
            "userLanguage" => $user?->getLanguage(),
            "defaultLanguage" => $this->languageService->getDefaultLanguage(),
            "trackingRecordHistory" => $this->getTrackingRecordsHistory($logisticUnitHistoryRecord),
        ]);
    }

    public function getTrackingRecordsHistory(LogisticUnitHistoryRecord $logisticUnitHistoryRecord): array {
        $user = $this->userService->getUser();
        return  [
            "id" => $logisticUnitHistoryRecord->getId(),
            "type" => $logisticUnitHistoryRecord->getType(),
            "date" => $this->formatService->datetime($logisticUnitHistoryRecord->getDate(), "", false, $user),
            "user" => $this->formatService->user($logisticUnitHistoryRecord->getUser()),
            "location" => $this->formatService->location($logisticUnitHistoryRecord->getLocation()),
            "message" => $logisticUnitHistoryRecord->getMessage()
        ];
    }

    public function getGroupHistoryForDatatable($pack, $params): array {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        $queryResult = $trackingMovementRepository->findTrackingMovementsForGroupHistory($pack, $params);

        $trackingMovements = $queryResult["data"];

        $rows = [];
        foreach ($trackingMovements as $trackingMovement) {
            $rows[] = $this->dataRowGroupHistory($trackingMovement);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult['filtered'],
            "recordsTotal" => $queryResult['total'],
        ];
    }

    public function dataRowPack(Pack $pack): array {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        $firstMovements = $trackingMovementRepository->findBy(
            ["pack" => $pack],
            ["datetime" => "ASC", "orderIndex" => "ASC", "id" => "ASC"],
            1
        );
        $fromColumnData = $this->trackingMovementService->getFromColumnData($firstMovements[0] ?? null);

        $lastMessage = $pack->getLastMessage();
        $hasPairing = !$pack->getPairings()->isEmpty() || $lastMessage;
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper())
            ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName()
            : null;
        $receptionAssociationFormatted = Stream::from($pack?->getReceiptAssociations())
            ->map(fn(ReceiptAssociation $receptionAssociation) => $receptionAssociation->getReceptionNumber())
            ->join(', ');
        $arrival = $pack->getArrivage();
        $truckArrival = $arrival?->getTruckArrival();

        $finalTrackingDelay = $this->formatTrackingDelayData($pack);

        $this->loadCache();

        $row = [
            'actions' => $this->getActionButtons($pack, $hasPairing),
            'cart' => $this->templating->render('pack/list/cart-column.html.twig', [
                'pack' => $pack,
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
            'details' => $this->templating->render('pack/list/content-pack-column.html.twig', [
                'pack' => $pack,
            ]),
            'code' => $this->templating->render("pack/list/code-column.html.twig", [
                "pack" => $pack,
            ]),
            'nature' => $this->formatService->nature($pack->getNature()),
            'quantity' => $pack->getQuantity() ?: 1,
            'project' => $this->formatService->project($pack->getProject()),
            'lastMovementDate' => $this->formatService->datetime($pack->getLastAction()?->getDatetime()),
            'origin' => $this->templating->render('tracking_movement/datatableMvtTracaRowFrom.html.twig', $fromColumnData),
            'ongoingLocation' => $this->formatService->location($pack->getLastOngoingDrop()?->getEmplacement()),
            'lastLocation' => $this->formatService->location($pack->getLastMovement()?->getEmplacement()),
            'receiptAssociation' => $receptionAssociationFormatted,
            'truckArrivalNumber' => $this->templating->render('pack/list/truck-arrival-column.html.twig', [
                'truckArrival' => $truckArrival
            ]),
            "trackingDelay" => $finalTrackingDelay["delayHTMLRaw"] ?? null,
            "limitTreatmentDate" => $finalTrackingDelay["dateHTML"] ?? null,
            "orderNumbers" => Stream::from($pack->getArrivage()?->getNumeroCommandeList() ?: [])
                ->join(', '),
            "supplier" => $this->formatService->supplier($pack->getArrivage()?->getFournisseur()),
            "carrier" => $this->formatService->carrier($pack->getArrivage()?->getTransporteur()),
            "group" => $pack->getGroup()
                ? $this->templating->render('tracking_movement/datatableMvtTracaRowFrom.html.twig', [
                    "entityPath" => "pack_show",
                    "entityId" => $pack->getGroup()?->getId(),
                    "from" => $this->formatService->pack($pack->getGroup()),
                ])
                : '',
        ];

        foreach ($this->cache['arrivalFreeFields'] ?? [] as $freeFieldId => $freeField) {
            $columnKey = $this->freeFieldService->getFreeFieldName($freeFieldId);
            $row[$columnKey] = $arrival ? $arrival->getFreeFieldValue($freeFieldId) : '';
        }

        foreach ($this->cache['trackingMovementFreeFields'] ?? [] as $freeFieldId => $freeField) {
            $columnKey = $this->freeFieldService->getFreeFieldName($freeFieldId);;
            $row[$columnKey] = $pack->getFreeFieldValue($freeFieldId);
        }

        return $row;
    }

    public function dataRowGroupHistory(TrackingMovement $trackingMovement): array {
        return [
            'group' => $trackingMovement->getPackGroup() ? ($this->formatService->pack($trackingMovement->getPackGroup()) . '-' . $trackingMovement->getGroupIteration()) : '',
            'date' => $this->formatService->datetime($trackingMovement->getDatetime(), "", false, $this->userService->getUser()),
            'type' => $this->formatService->status($trackingMovement->getType())
        ];
    }

    public function checkPackDataBeforeEdition(InputBag $data, int $isGroup): array {
        $quantity = $data->getInt('quantity', 0);
        $weight = !empty($data->get('weight')) ? str_replace(",", ".", $data->get('weight')) : null;
        $volume = !empty($data->get('volume')) ? str_replace(",", ".", $data->get('volume')) : null;

        if (!$isGroup && $quantity <= 0) {
            return [
                'success' => false,
                'msg' => 'La quantité doit être supérieure à 0.'
            ];
        }

        if (!empty($weight) && (!is_numeric($weight) || ((float)$weight) <= 0)) {
            return [
                'success' => false,
                'msg' => 'Le poids doit être un nombre valide supérieur à 0.'
            ];
        }

        if (!empty($volume) && (!is_numeric($volume) || ((float)$volume) <= 0)) {
            return [
                'success' => false,
                'msg' => 'Le volume doit être un nombre valide supérieur à 0.'
            ];
        }

        return [
            'success' => true,
            'msg' => 'OK',
        ];
    }

    public function editPack(EntityManagerInterface $entityManager,
                             InputBag               $data,
                             Pack                   $pack,
                             bool                   $isGroup = false): void {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $projectRepository = $entityManager->getRepository(Project::class);


        $natureId = $data->get('nature');
        $comment = $data->get('comment');
        $weight = !empty($data->get('weight')) ? str_replace(",", ".", $data->get('weight')) : null;
        $volume = !empty($data->get('volume')) ? str_replace(",", ".", $data->get('volume')) : null;
        $recordDate = new DateTime();

        $nature = $natureId ? $natureRepository->find($natureId) : null;

        if (!$isGroup) {
            $projectId = $data->get('projects');
            $quantity = $data->get('quantity');

            $project = $projectRepository->findOneBy(["id" => $projectId]);
            $this->projectHistoryRecordService->changeProject($entityManager, $pack, $project, $recordDate);
            foreach($pack->getChildArticles() as $article) {
                $this->projectHistoryRecordService->changeProject($entityManager, $article, $project, $recordDate);
            }

            $pack
                ->setQuantity($quantity);

        }

        $pack
            ->setWeight($weight)
            ->setVolume($volume)
            ->setComment($comment);

        if (!$pack->isGroup()
            || $this->settingsService->getValue($entityManager, Setting::GROUP_GET_CHILD_TRACKING_DELAY) != 1) {
            $pack->setNature($nature);
        }
    }

    public function createPack(EntityManagerInterface $entityManager,
                               array                  $options = [],
                               Utilisateur            $user = null): Pack {
        if (!empty($options['code'])) {
            $pack = $this->createPackWithCode($options['code']);
        } else {
            /** @var ?Project $project */
            $project = $options['project'] ?? null;

            if(isset($options['arrival'])) {
                /** @var Arrivage $arrival */
                $arrival = $options['arrival'];

                /** @var Nature $nature */
                $nature = $options['nature'];

                $arrivalNum = $arrival->getNumeroArrivage();
                $counter = $this->getNextPackCodeForArrival($arrival) + 1;
                $counterStr = sprintf("%03u", $counter);

                $code = (($nature->getPrefix() ?? '') . $arrivalNum . $counterStr ?? '');

                $pack = $this
                    ->createPackWithCode($code)
                    ->setNature($nature)
                    ->setTruckArrivalDelay($options['truckArrivalDelay'] ?? 0);

                if (isset($options['reception'])) {
                    /** @var Reception $reception */
                    $reception = $options['reception'];
                    $this->receptionLineService->persistReceptionLine($entityManager, $reception, $pack);
                }

                $arrival->addPack($pack);

                $this->persistLogisticUnitHistoryRecord($entityManager, $pack, [
                    "message" => $this->formatService->list($this->arrivageService->serialize($arrival)),
                    "historyDate" => new DateTime(),
                    "user" => $user,
                    "type" => "Arrivage UL",
                    "location" => $arrival->getDropLocation(),
                ]);

                $truckArrival = $arrival->getTruckArrival();

                if ($truckArrival) {
                    $this->persistLogisticUnitHistoryRecord($entityManager, $pack, [
                        "message" => $this->formatService->list($this->truckArrivalService->serialize($truckArrival)),
                        "historyDate" => $truckArrival->getCreationDate(),
                        "user" => $user,
                        "type" => "Arrivage Camion",
                        "location" => $truckArrival->getUnloadingLocation(),
                    ]);
                }
            }
            else if (isset($options['orderLine'])) {
                /** @var Nature $nature */
                $nature = $options['nature'];

                /** @var TransportDeliveryOrderPack $orderLine */
                $orderLine = $options['orderLine'];
                $order = $orderLine->getOrder();
                $request = $order->getRequest();

                $requestNumber = $request->getNumber();
                $naturePrefix = $nature->getPrefix() ?? '';
                $counter = $order->getPacks()->count();
                $counterStr = sprintf("%03u", $counter);

                $code = $naturePrefix . $requestNumber . $counterStr;
                $pack = $this
                    ->createPackWithCode($code)
                    ->setNature($nature);

                $orderLine->setPack($pack);
            }
            else {
                throw new RuntimeException('Unhandled pack configuration');
            }

            if ($project) {
                $recordDate = new DateTime();
                $this->projectHistoryRecordService->changeProject($entityManager, $pack, $project, $recordDate);

                foreach($pack->getChildArticles() as $article) {
                    $this->projectHistoryRecordService->changeProject($entityManager, $article, $project, $recordDate);
                }
            }
        }
        return $pack;
    }

    public function createPackWithCode(string $code): Pack {
        $pack = new Pack();
        $pack->setCode(str_replace("    ", " ", $code));
        return $pack;
    }

    public function persistPack(EntityManagerInterface $entityManager,
                                                       $packOrCode,
                                                       $quantity,
                                                       $natureId = null,
                                bool                   $onlyPack = false,
                                array                  $options = []): Pack
    {

        $packRepository = $entityManager->getRepository(Pack::class);

        $codePack = $packOrCode instanceof Pack ? $packOrCode->getCode() : $packOrCode;

        $pack = ($packOrCode instanceof Pack)
            ? $packOrCode
            : $packRepository->findOneBy(['code' => $packOrCode]);

        if ($onlyPack && $pack && $pack->isGroup()) {
            throw new Exception(Pack::PACK_IS_GROUP);
        }

        $fromPackSplit = $options['fromPackSplit'] ?? null;

        if ($fromPackSplit && $pack) {
            throw new Exception("Le colis {$codePack} est déjà présent en base de données.");
        }

        if (!isset($pack)) {
            $pack = $this->createPackWithCode($codePack);
            $pack->setQuantity($quantity);
            $entityManager->persist($pack);
        }

        if (!empty($natureId)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $nature = $natureRepository->find($natureId);

            if (!empty($nature)) {
                $pack->setNature($nature);
            }
        }

        return $pack;
    }

    public function createMultiplePacks(EntityManagerInterface $entityManager,
                                        Arrivage               $arrivage,
                                        array                  $packByNatures,
                                                               $user,
                                        bool                   $persistTrackingMovements = true,
                                        Project                $project = null,
                                        Reception              $reception = null): array {
        $natureRepository = $entityManager->getRepository(Nature::class);

        $location = $persistTrackingMovements
            ? $arrivage->getDropLocation()
            : null;

        $totalPacks = Stream::from($packByNatures)->filter()->sum();
        if($totalPacks > 500) {
            throw new FormException("Vous ne pouvez pas ajouter plus de 500 UL");
        }

        $now = new DateTime('now');
        $createdPacks = [];

        $truckArrival = $arrivage->getTruckArrival();
        if ($truckArrival) {
            $truckArrivalCreationDate = $truckArrival->getCreationDate();

            $interval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $truckArrivalCreationDate, new DateTime("now"));
            $delay = $this->dateTimeService->convertDateIntervalToMilliseconds($interval);
        }

        foreach ($packByNatures as $natureId => $number) {
            if ($number) {
                $nature = $natureRepository->find($natureId);
                for ($i = 0; $i < $number; $i++) {
                    $pack = $this->createPack($entityManager, [
                        "arrival" => $arrivage,
                        "nature" => $nature,
                        "project" => $project,
                        "reception" => $reception,
                        "truckArrivalDelay" => $delay ?? null
                    ], $user);
                    if ($persistTrackingMovements && isset($location)) {
                        $this->trackingMovementService->persistTrackingForArrivalPack(
                            $entityManager,
                            $pack,
                            $location,
                            $user,
                            $now,
                            $arrivage
                        );
                    }
                    // pack persisted by Arrival cascade persist
                    $createdPacks[] = $pack;
                }
            }
        }
        return $createdPacks;
    }

    public function launchPackDeliveryReminder(EntityManagerInterface $entityManager): void {
        $packRepository = $entityManager->getRepository(Pack::class);
        $waitingDaysRequested = [7, 15, 30, 42];
        $ongoingPacks = $packRepository->findOngoingPacksOnDeliveryPoints($waitingDaysRequested);
        foreach ($ongoingPacks as $packData) {
            /** @var Pack $pack */
            $pack = $packData[0];
            $waitingDays = $packData['packWaitingDays'];

            $remindPosition = array_search($waitingDays, $waitingDaysRequested);
            $titleSuffix = match($remindPosition) {
                0 => ' - 1ère relance',
                1 => ' - 2ème relance',
                2 => ' - 3ème relance',
                3 => ' - dernière relance',
                default => ''
            };
            $arrival = $pack->getArrivage();
            $lastOngoingDrop = $pack->getLastOngoingDrop();

            $this->mailerService->sendMail(
                $entityManager,
                $this->translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR. "Unité logistique non récupéré$titleSuffix",
                $this->templating->render('mails/contents/mailPackDeliveryDone.html.twig', [
                    'title' => 'Votre unité logistique est toujours présente dans votre magasin',
                    'orderNumber' => implode(', ', $arrival->getNumeroCommandeList()),
                    'pack' => $this->formatService->pack($pack),
                    'emplacement' => $lastOngoingDrop->getEmplacement(),
                    'date' => $lastOngoingDrop->getDatetime(),
                    'fournisseur' => $this->formatService->supplier($arrival->getFournisseur()),
                    'pjs' => $arrival->getAttachments()
                ]),
                $arrival->getReceivers()->toArray()
            );
        }
    }

    public function getNextPackCodeForArrival(Arrivage $arrival): int {
        $lastPack = $arrival->getPacks()->last();

        $counter = 0;
        if($lastPack) {
            $counter = (int) substr($lastPack->getCode(), -3);
        }

        return $counter;
    }

    public function getArrivalPackColumnVisibleConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getFieldModes('arrivalPack');
        return $this->fieldModesService->getArrayConfig(
            [
                ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true, "searchable" => true],
                ["name" => 'nature', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Nature'), "searchable" => true],
                ["name" => 'code', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Unités logistiques'), "searchable" => true],
                ["name" => 'project', 'title' => $this->translationService->translate('Référentiel', 'Projet', 'Projet', false), "searchable" => true],
                ["name" => 'lastMvtDate', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Date dernier mouvement'), "searchable" => true],
                [
                    "name" => 'ongoingLocation',
                    "title" => $this->translationService->translate('Traçabilité', 'Général', 'Emplacement encours'),
                    "info" => $this->translationService->translate("Traçabilité", "Général", "Emplacement sur lequel se trouve l'unité logistique actuellement", false),
                    "searchable" => true,
                ],
                ["name" => 'operator', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Opérateur'), "searchable" => true],
            ],
            [],
            $columnsVisible,
        );
    }

    public function getPackListColumnVisibleConfig(Utilisateur $currentUser,
                                                   EntityManagerInterface $entityManager): array {
        $columnsVisible = $currentUser->getFieldModes(FieldModesController::PAGE_PACK_LIST) ?? Utilisateur::DEFAULT_PACK_LIST_FIELDS_MODES;
        $hasRightAddToCart = $this->userService->hasRightFunction(Menu::GENERAL, Action::SHOW_CART);

        $this->loadCache();

        $freeFields = Stream::from(
            $this->cache['arrivalFreeFields'],
            $this->cache['trackingMovementFreeFields']
        )
            ->toArray();

        return $this->fieldModesService->getArrayConfig(
            [
                ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true, "searchable" => true],
                ["name" => 'details', "title" => '<span class="fa fa-search"><span>', "className" => 'noVis', "orderable" => false],
                ...$hasRightAddToCart
                    ? [
                        ['name' => 'cart', 'title' => '<span class="wii-icon wii-icon-cart add-all-cart"></span>', 'classname' => 'cart-row', "orderable" => false],
                    ]
                    : [],
                ['name' => 'pairing', 'title' => '<span class="wii-icon wii-icon-pairing black"><span>', 'classname' => 'pairing-row'],
                ['name' => 'code', 'title' => $this->translationService->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Numéro d\'UL')],
                ['name' => 'nature', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Nature')],
                ['name' => 'quantity', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Quantité')],
                ['name' => 'project', 'title' => $this->translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Projet')],
                ['name' => 'lastMovementDate', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Date dernier mouvement')],
                ['name' => 'origin', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Issu de'), 'orderable' => false],
                [
                    'name' => 'ongoingLocation',
                    'title' => $this->translationService->translate('Traçabilité', 'Général', 'Emplacement encours'),
                    'info' => $this->translationService->translate("Traçabilité", "Général", "Emplacement sur lequel se trouve l'unité logistique actuellement", false),
                ],
                [
                    'name' => 'lastLocation',
                    'title' => $this->translationService->translate('Traçabilité', 'Général', 'Dernier emplacement'),
                ],
                ['name' => 'orderNumbers', 'title' => $this->translationService->translate('Arrivages UL', 'Champs fixes', 'N° commande / BL'), 'orderable' => false],
                ['name' => 'supplier', 'title' => $this->translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Fournisseur')],
                ['name' => 'carrier', 'title' => $this->translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Transporteur')],
                ['name' => 'receiptAssociation', 'title' => 'Association', 'orderable' => false],
                ['name' => 'truckArrivalNumber', 'title' => 'Arrivage camion'],
                ['name' => 'trackingDelay', 'title' => $this->translationService->translate('Traçabilité', 'Unités logistiques', 'Divers', 'Délai de traitement'), "orderable" => false],
                ['name' => 'limitTreatmentDate', 'title' => 'Date limite de traitement'],
                ['name' => 'group', 'title' =>  'Groupe rattaché'],
            ],
            $freeFields,
            $columnsVisible,
        );
    }

    public function updateArticlePack(EntityManagerInterface $entityManager,
                                      Article                $article): Pack {
        $trackingPack = $article->getTrackingPack();
        if(!isset($trackingPack)) {
            $packRepository = $entityManager->getRepository(Pack::class);
            $trackingPack = $packRepository->findOneBy(["code" => $article->getBarCode()])
                ?? $this->persistPack($entityManager, $article->getBarCode(), $article->getQuantite());
            $article->setTrackingPack($trackingPack);
        }
        return $trackingPack;
    }

    public function getBarcodePackConfig(Pack                   $pack,
                                         Utilisateur|array|null $receivers = null,
                                         ?string                $packIndex = '',
                                         ?bool                  $typeArrivalParamIsDefined = false,
                                         ?bool                  $usernameParamIsDefined = false,
                                         ?bool                  $dropzoneParamIsDefined = false,
                                         ?bool                  $packCountParamIsDefined = false,
                                         ?bool                  $commandAndProjectNumberIsDefined = false,
                                         ?array                 $firstCustomIconConfig = null,
                                         ?array                 $secondCustomIconConfig = null,
                                         ?bool                  $showTypeLogoArrivalUl = null,
                                         ?bool                  $businessUnitParam = false,
                                         ?bool                  $projectParam = false,
                                         ?bool                  $showDateAndHourArrivalUl = false,
                                         ?bool                  $showTruckArrivalDateAndHour = false,
                                         ?bool                  $showTruckArrivalDateAndHourBarcode = false,
                                         ?bool                  $showPackNature = false,
                                         ?bool                  $showLimitTreatmentDate = false,
    ): array
    {

        $arrival = $pack->getArrivage();

        $dateAndHour = $this->formatService->datetime($arrival->getDate());
        $truckArrivalDateAndHour = $this->formatService->datetime($arrival->getTruckArrival()?->getCreationDate());

        $businessUnit = $businessUnitParam
            ? $arrival->getBusinessUnit()
            : '';

        $project = $projectParam
            ? $pack->getProject()?->getCode()
            : '';

        $arrivalType = $typeArrivalParamIsDefined
            ? $this->formatService->type($arrival->getType())
            : '';

        $receivers = $receivers
            ? (is_array($receivers)
                ? $receivers
                : [$receivers]
            ) : [];

        $receiverUsernames = ($usernameParamIsDefined && !empty($receivers))
            ? Stream::from($receivers)->map(fn(Utilisateur $receiver) => $this->formatService->user($receiver))->join(", ")
            : '';
        $receiverDropzones = Stream::from($receivers)
            ->filter(static fn(Utilisateur $receiver) => $receiver->getDropzone())
            ->map(function(Utilisateur $receiver) use ($usernameParamIsDefined) {
                $dropZone = $receiver->getDropzone();
                $userLabel = (!$usernameParamIsDefined ? "{$this->formatService->user($receiver)}: " : "");

                if ($dropZone instanceof Emplacement) {
                    $locationLabel = $this->formatService->location($dropZone);
                } elseif ($dropZone instanceof LocationGroup) {
                    $locationLabel = $this->formatService->locationGroup($dropZone);
                } else {
                    $locationLabel = "";
                }
                return $userLabel.$locationLabel;
            })
            ->join(", ");

        $dropZoneLabel = ($dropzoneParamIsDefined && !empty($receiverDropzones))
            ? $receiverDropzones
            : '';

        $arrivalCommand = [];
        $arrivalLine = "";
        $i = 0;
        foreach($arrival?->getNumeroCommandeList() ?? [] as $command) {
            $arrivalLine .= $command;

            if(++$i % 4 == 0) {
                $arrivalCommand[] = $arrivalLine;
                $arrivalLine = "";
            } else {
                $arrivalLine .= " ";
            }
        }

        if(!empty($arrivalLine)) {
            $arrivalCommand[] = $arrivalLine;
        }

        $arrivalProjectNumber = $arrival
            ? ($arrival->getProjectNumber() ?? '')
            : '';

        $packLabel = ($packCountParamIsDefined ? $packIndex : '');

        $usernameSeparator = ($receiverUsernames && $dropZoneLabel) ? ' / ' : '';

        $labels = [$arrivalType];

        $labels[] = $receiverUsernames . $usernameSeparator . $dropZoneLabel;

        if ($commandAndProjectNumberIsDefined) {
            if ($arrivalCommand && $arrivalProjectNumber) {
                if(count($arrivalCommand) > 1) {
                    $labels = array_merge($labels, $arrivalCommand);
                    $labels[] = $arrivalProjectNumber;
                } else if(count($arrivalCommand) == 1) {
                    $labels[] = $arrivalCommand[0] . ' / ' . $arrivalProjectNumber;
                }
            } else if ($arrivalCommand) {
                $labels = array_merge($labels, $arrivalCommand);
            } else if ($arrivalProjectNumber) {
                $labels[] = $arrivalProjectNumber;
            }
        }

        if($businessUnitParam) {
            $labels[] = $businessUnit;
        }

        if($projectParam) {
            $labels[] = $project;
        }

        if($showDateAndHourArrivalUl) {
            $labels[] = $dateAndHour;
        }

        if($showTruckArrivalDateAndHourBarcode && $truckArrivalDateAndHour) {
            $barcodeConfig = $this->settingsService->getDimensionAndTypeBarcodeArray($this->entityManager);
            $isCode128 = $barcodeConfig["isCode128"];
            $labels[] = [
                "barcode" => [
                    "code" => $truckArrivalDateAndHour,
                    "height" => 48,
                    "width" => $isCode128 ? 1 : 48,
                    "type" => $isCode128 ? 'c128' : 'qrcode',
                ],
            ];
        } else if($showTruckArrivalDateAndHour) {
            $labels[] = $truckArrivalDateAndHour;
        }

        if($showPackNature){
            $labels[] = $pack->getNature() ? $pack->getNature()->getLabel() : '';
        }

        if($showLimitTreatmentDate) {
            $finalTrackingDelay = $this->formatTrackingDelayData($pack);
            $labels[] = $finalTrackingDelay["dateHTML"] ?? null;
        }

        if ($packLabel) {
            $labels[] = $packLabel;
        }

        $typeLogoPath = $showTypeLogoArrivalUl ? $arrival->getType()?->getLogo()?->getFullPath() : null;

        return [
            'code' => $pack->getCode(),
            'labels' => $labels,
            'firstCustomIcon' => $arrival?->getCustoms() ? $firstCustomIconConfig : null,
            'secondCustomIcon' => $arrival?->getIsUrgent() ? $secondCustomIconConfig : null,
            'typeLogoArrivalUl' => $typeLogoPath,
        ];
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Pack $logisticUnit
     * @param array{message: string, historyDate: DateTime, user: Utilisateur, type: string, location?: Emplacement} $data
     * @return void
     */
    public function persistLogisticUnitHistoryRecord(EntityManagerInterface $entityManager,
                                                     Pack                   $logisticUnit,
                                                     array                  $data): void {

        $message = $data["message"];
        $historyDate = $data["historyDate"];
        $user = $data["user"];
        $type = $data["type"];
        $location = $data["location"] ?? null;

        $logisticUnitHistoryRecord = (new LogisticUnitHistoryRecord())
            ->setMessage($message)
            ->setPack($logisticUnit)
            ->setStatusDate($historyDate)
            ->setDate($historyDate)
            ->setType($type)
            ->setLocation($location)
            ->setUser($user);

        $entityManager->persist($logisticUnitHistoryRecord);
    }

    /**
     * @return array{
     *     color?: string,
     *     delay?: int,
     *     delayHTML?: string,
     *     delayHTMLRaw?: string,
     *     date?: DateTime,
     *     dateHTML?: string,
     * }
     */
    public function formatTrackingDelayData(Pack $pack): array {
        $packTrackingDelay = $pack->getCurrentTrackingDelay();

        $remainingTime = $this->getTrackingDelayRemainingTime($pack);

        if (!isset($remainingTime)) {
            return [];
        }

        if (!$packTrackingDelay->isTimerPaused()) {
            $limitTreatmentDate = $packTrackingDelay->getLimitTreatmentDate();
        }

        $color = $this->getTrackingDelayColor($pack, $remainingTime);

        $remainingInterval = $this->dateTimeService->secondsToDateInterval($remainingTime);
        $remainingInterval->invert = $remainingTime < 0;
        $delayHTML = $this->formatService->delay($remainingInterval);

        return [
            "color" => $color,
            "delay" => $remainingTime,
            "delayHTML" => $delayHTML,
            "delayHTMLRaw" => $this->templating->render("pack/tracking-delay.html.twig", [
                "delay" => $delayHTML,
                "color" => $color,
            ]),
            "date" => $limitTreatmentDate ?? null,
            "dateHTML" => $this->formatService->datetime($limitTreatmentDate ?? null, null),
        ];
    }

    /**
     * Return the child of the group which have the shortest tracking delay.
     *
     * @param array<Pack> $inAdditionChildren children not already in the group in database
     */
    public function getChildPackWithShortestDelay(Pack  $group,
                                                  array $inAdditionChildren = []): ?Pack {
        $packChildSortedByDelay = Stream::from($group->getContent(), $inAdditionChildren)
            ->unique()
            ->filterMap(function(Pack $pack) {
                $remainingTime = $this->getTrackingDelayRemainingTime($pack);
                // ignore pack without tracking delay
                return isset($remainingTime)
                    ? [
                        "pack" => $pack,
                        "remainingTime" => $remainingTime,
                    ]
                    : null;
            })
            ->sort(static fn(array $data1, array $data2) => ($data1["remainingTime"] <=> $data2["remainingTime"]));

        return $packChildSortedByDelay->first()["pack"] ?? null;
    }


    public function getTrackingDelayRemainingTime(Pack $pack): ?int {
        $packTrackingDelay = $pack->getCurrentTrackingDelay();
        $nature = $pack->getNature();
        $natureTrackingDelay = $nature?->getTrackingDelay();

        if(!$packTrackingDelay || !$natureTrackingDelay) {
            return null;
        }

        $elapsedTime = $packTrackingDelay->getElapsedTime();
        if (!$packTrackingDelay->isTimerPaused()) {
            $trackingDelay = $this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $packTrackingDelay->getCalculatedAt(), new DateTime());
            $trackingDelayInSeconds = ($elapsedTime + floor($this->dateTimeService->convertDateIntervalToMilliseconds($trackingDelay) / 1000));
        }
        else {
            $trackingDelayInSeconds = $elapsedTime;
        }

        return $natureTrackingDelay - $trackingDelayInSeconds;
    }

    private function getTrackingDelayColor(Pack $pack,
                                           int  $remainingTime): ?string {
        $nature = $pack->getNature();

        if ($remainingTime < 0){
            $trackingDelayColor = $nature->getExceededDelayColor() ?? PackService::PACK_DEFAULT_TRACKING_DELAY_COLOR;
        }
        else {
            $trackingDelaySegments = $nature->getTrackingDelaySegments();
            $trackingDelayColor = PackService::PACK_DEFAULT_TRACKING_DELAY_COLOR;

            foreach ($trackingDelaySegments as $trackingDelaySegment){
                $segmentDelayInSeconds = $this->dateTimeService->calculateSecondsFrom($trackingDelaySegment["segmentMax"], Nature::TRACKING_DELAY_REGEX, "h");
                if($remainingTime <= $segmentDelayInSeconds){
                    $trackingDelayColor = $trackingDelaySegment["segmentColor"];
                    break;
                }
            }
        }

        return $trackingDelayColor;
    }

    public function getFormatedKeyboardPackGenerator(Iterator $packs, bool $completeMatch): Iterator {
        if (!$completeMatch) {
            $firstElement = [
                "id" => "create-new",
                "html" => "<div class='create-new-container'><span class='wii-icon wii-icon-plus'></span> <b>Nouvelle unité logistique</b></div>",
            ];

            $firstElement["highlighted"] = !$packs->valid();

            yield $firstElement;
        }

        if ($packs->valid()) {
            foreach ($packs as $pack) {
                // if this is the first element, highlight it
                $pack["highlighted"] = !isset($firstElement["highlighted"]);

                $pack["stripped_comment"] = $this->formatService->html($pack["comment"] ?? '');
                $pack["lastMvtDate"] = $this->formatService->datetime(DateTime::createFromFormat('d/m/Y H:i', $pack['lastMvtDate'])
                    ?: null);

                yield $pack;
            }
        }
    }

    public function getCsvHeader(): array {
        $this->loadCache();

        $freeFields = Stream::from(
            $this->cache['arrivalFreeFields'],
            $this->cache['trackingMovementFreeFields']
        )
            ->toArray();

        $header =  [
            $this->translationService->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "Numéro d'UL", false),
            $this->translationService->translate('Traçabilité', 'Général', 'Nature', false),
            $this->translationService->translate( 'Traçabilité', 'Général', 'Date dernier mouvement', false),
            $this->translationService->translate( 'Traçabilité', 'Général', 'Issu de', false),
            $this->translationService->translate( 'Traçabilité', 'Général', 'Issu de (numéro)', false),
            $this->translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Fournisseur', false),
            $this->translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Transporteur', false),
            $this->translationService->translate('Arrivages UL', 'Champs fixes', 'N° commande / BL', false),
            $this->translationService->translate( 'Traçabilité', 'Général', 'Emplacement', false),
            'Groupe rattaché',
            'Groupe',
            'Poids (kg)',
            'Volume (m3)',
        ];

        foreach ($freeFields as $freeField) {
            $header[] = $freeField->getLabel();
        }

        return $header;
    }


    public function getExportPacksFunction(DateTime               $dateTimeMin,
                                           DateTime               $dateTimeMax,
                                           EntityManagerInterface $entityManager): callable {
        $packRepository = $entityManager->getRepository(Pack::class);
        $packs = $packRepository->iteratePacksByDates($dateTimeMin, $dateTimeMax);

        return function ($handle) use ($packs) {
            foreach ($packs as $pack) {
                $mvtData = $this->trackingMovementService->getFromColumnData([
                    'entity' => $pack['entity'],
                    'entityId' => $pack['entityId'],
                    'entityNumber' => $pack['entityNumber'],
                ]);

                $pack = Stream::from($mvtData, $pack)->toArray();
                $this->putPackLine($handle, $pack);
            }
        };
    }

    public function putPackLine($handle, array $pack): void {
        $this->loadCache();

        $line = Stream::from(
            [
                $pack['code'],
                $pack['nature'],
                $this->formatService->datetime($pack['lastMvtDate'], "", false, $this->userService->getUser()),
                $pack['fromLabel'],
                $pack['from'],
                $pack['supplier'] ?? null,
                $pack['carrier'] ?? null,
                Stream::from($pack['orderNumbers'] ?? [])->join(' / ') ?: null,
                $pack['location'],
                $pack['groupCode'],
                $this->formatService->bool($pack['groupIteration'] ?? false),
                $pack['weight'],
                $pack['volume'],
            ],
            Stream::from($this->cache['arrivalFreeFields'])
                ->map(static fn (FreeField $freeField) => $pack['arrivalFreeFieldsValues'][$freeField->getId()] ?? '')
                ->toArray(),
            Stream::from($this->cache['trackingMovementFreeFields'])
                ->map(static fn (FreeField $freeField) => $pack['packFreeFieldsValues'][$freeField->getId()] ?? '')
                ->toArray(),
        )
            ->toArray();

        $this->CSVExportService->putLine($handle, $line);
    }

    public function getActionButtons(Pack $pack, bool $hasPairing): string {
        $isGroup = $pack->getGroupIteration() || !$pack->getContent()->isEmpty();

        return $this->templating->render('utils/action-buttons/dropdown.html.twig', [
            'actions' => [
                [
                    'hasRight' => $pack->getArrivage(),
                    'title' => $this->translationService->translate('Général', null, 'Zone liste', 'Imprimer'),
                    'icon' => 'wii-icon wii-icon-printer-black',
                    'href' => $this->router->generate('print_arrivage_single_pack_bar_codes', [
                        'arrivage' => $pack->getArrivage()?->getId(),
                        'pack' => $pack->getId()
                    ]),
                ],
                [
                    'hasRight' => $this->userService->hasRightFunction(Menu::TRACA, Action::EDIT),
                    'title' => $this->translationService->translate('Général', null, 'Modale', 'Modifier'),
                    'icon' => 'fas fa-edit',
                    'attributes' => [
                        'data-toggle' => "modal",
                        'data-target' => "#modalEditPack",
                        'data-id' => $pack->getId(),
                    ]
                ],
                [
                    'hasRight' => !$isGroup && $this->userService->hasRightFunction(Menu::TRACA, Action::DELETE) ,
                    'title' => $this->translationService->translate('Général', null, 'Modale', 'Supprimer'),
                    'icon' => 'wii-icon wii-icon-trash-black',
                    'class' => 'delete-pack',
                    'attributes' => [
                        'data-id' => $pack->getId(),
                    ]
                ],
                [
                    'hasRight' => $isGroup && $this->userService->hasRightFunction(Menu::TRACA, Action::EDIT),
                    'title' => $this->translationService->translate('Traçabilité', 'Unités logistiques', 'Onglet "Groupes"', 'Dégrouper'),
                    'icon' => 'wii-icon wii-icon-trash-black',
                    'attributes' => [
                        'data-toggle' => "modal",
                        'data-target' => "#modalUngroup",
                        'data-id' => $pack->getId(),
                    ]
                ],
                [
                    'hasRight' => $hasPairing && $this->userService->hasRightFunction(Menu::IOT, Action::DISPLAY_SENSOR),
                    'title' => $this->translationService->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Historique des données'),
                    'icon' => 'wii-icon wii-icon-pairing wii-icon-black',
                    'href' => $this->router->generate('show_data_history', [ 'type' => Sensor::PACK, 'id' => $pack->getId() ]),
                ],
                [
                    'hasRight' => !$isGroup,
                    'title' => 'Recalculer le délai de traça',
                    'class' => 'reload-tracking-delay',
                    'icon' => 'wii-icon wii-icon-modif wii-icon-17px-black',
                    'attributes' => [
                        'data-id' => $pack->getId(),
                    ]
                ],
                [
                    'hasRight' => true,
                    'title' => "Détails",
                    'icon' => 'fa fa-eye',
                    'href' => $this->router->generate('pack_show', ['id' => $pack->getId()]),
                    'actionOnClick' => true,
                ],
                [
                    'hasRight' => true,
                    'title' => "Voir les mouvements",
                    'icon' => 'fa fa-eye',
                    'href' => $this->router->generate('mvt_traca_index', [ 'pack' => $pack->getCode()]),
                ],
            ],
        ]);
    }

    public function updateTrackingDelayWithPackCode(EntityManagerInterface $entityManager,
                                                    string                 $packCode): bool {
        if (!$packCode) {
            return false;
        }

        $packRepository = $entityManager->getRepository(Pack::class);
        $packOrGroup = $packRepository->findOneBy(["code" => $packCode]);

        if (!$packOrGroup) {
            return false;
        }

        if ($packOrGroup->isGroup()){
            $pack = $this->getChildPackWithShortestDelay($packOrGroup);
            $trackingDelay = $pack?->getCurrentTrackingDelay();

            // clear columns if there is no pack in the group
            $packOrGroup
                ->setCurrentTrackingDelay($trackingDelay)
                ->setNature($pack?->getNature());
        }
        else {
            // if it's a simple pack we calculate its tracking delay
            $this->trackingDelayService->updatePackTrackingDelay($entityManager, $packOrGroup);
        }

        $entityManager->flush();
        return true;
    }

    private function loadCache(): void {
        $this->cache['arrivalFreeFields'] ??= $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategoryType::ARRIVAGE, CategorieCL::ARRIVAGE);
        $this->cache['trackingMovementFreeFields'] ??= $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::MVT_TRACA, CategoryType::MOUVEMENT_TRACA);
    }
}
