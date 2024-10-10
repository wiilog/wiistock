<?php


namespace App\Service;

use App\Controller\FieldModesController;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Language;
use App\Entity\LocationGroup;
use App\Entity\OperationHistory\LogisticUnitHistoryRecord;
use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\ReceiptAssociation;
use App\Entity\Reception;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Nature;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\LanguageHelper;
use App\Repository\PackRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Iterator;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class PackService {

    private const PACK_DEFAULT_TRACKING_DELAY_COLOR = "#000000";

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public Security $security;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public FieldModesService $fieldModesService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public ReceptionLineService $receptionLineService;

    #[Required]
    public DateTimeService $dateTimeService;

    #[Required]
    public SettingsService $settingsService;

    #[Required]
    public ArrivageService $arrivageService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public ProjectHistoryRecordService $projectHistoryRecordService;

    #[Required]
    public TruckArrivalService $truckArrivalService;

    #[Required]
    public UserService $userService;

    public function getDataForDatatable($params = null): array
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $packRepository = $this->entityManager->getRepository(Pack::class);
        $currentUser = $this->security->getUser();

        $filters = $params->get("codeUl") ? [["field"=> "UL", "value"=> $params->get("codeUl")]] : $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $this->security->getUser());
        $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $currentUser->getLanguage() ?: $defaultLanguage;
        $queryResult = $packRepository->findByParamsAndFilters($params, $filters, PackRepository::PACKS_MODE, [
            'defaultLanguage' => $defaultLanguage,
            'language' => $language,
            'fields' => $this->getPackListColumnVisibleConfig($currentUser),
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

    public function generateTrackingHistoryHtml(EntityManagerInterface $entityManager, LogisticUnitHistoryRecord $logisticUnitHistoryRecord, int $firstRecord, int $latestRecordId): string {
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

    public function getGroupHistoryForDatatable($pack, $params) {
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

    public function dataRowPack(Pack $pack): array
    {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        $firstMovements = $trackingMovementRepository->findBy(
            ["pack" => $pack],
            ["datetime" => "ASC", "orderIndex" => "ASC", "id" => "ASC"],
            1
        );
        $fromColumnData = $this->trackingMovementService->getFromColumnData($firstMovements[0] ?? null);
        $user = $this->security->getUser();
        $prefix = $user && $user->getDateFormat() ? $user->getDateFormat() : 'd/m/Y';
        $lastMessage = $pack->getLastMessage();
        $hasPairing = !$pack->getPairings()->isEmpty() || $lastMessage;
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper())
            ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName()
            : null;
        $receptionAssociationFormatted = Stream::from($pack?->getReceiptAssociations())
            ->map(fn(ReceiptAssociation $receptionAssociation) => $receptionAssociation->getReceptionNumber())
            ->join(', ');
        $arrival = $pack->getArrivage();
        $truckArrival = $arrival
            ? $arrival->getTruckArrival() ?? ($arrival->getTruckArrivalLines()->first() ? $arrival->getTruckArrivalLines()->first()?->getTruckArrival() : null)
            : null ;

        $finalTrackingDelay = $this->generateTrackingDelayHtml($pack);

        /** @var TrackingMovement $lastPackMovement */
        $lastPackMovement = $pack->getLastAction();
        return [
            'actions' => $this->templating->render('pack/datatablePackRow.html.twig', [
                'pack' => $pack,
                'hasPairing' => $hasPairing
            ]),
            'cart' => $this->templating->render('pack/cart-column.html.twig', [
                'pack' => $pack,
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
            'details' => $this->templating->render('pack/content-pack-column.html.twig', [
                'pack' => $pack,
            ]),
            'code' => $this->templating->render("pack/logisticUnitColumn.html.twig", [
                "pack" => $pack,
            ]),
            'nature' => $this->formatService->nature($pack->getNature()),
            'quantity' => $pack->getQuantity() ?: 1,
            'project' => $pack->getProject()?->getCode(),
            'lastMovementDate' => $lastPackMovement
                ? ($lastPackMovement->getDatetime()
                    ? $lastPackMovement->getDatetime()->format($prefix . ' \à H:i:s')
                    : '')
                : '',
            'origin' => $this->templating->render('tracking_movement/datatableMvtTracaRowFrom.html.twig', $fromColumnData),
            'location' => $lastPackMovement
                ? ($lastPackMovement->getEmplacement()
                    ? $lastPackMovement->getEmplacement()->getLabel()
                    : '')
                : '',
            'receiptAssociation' => $receptionAssociationFormatted,
            'truckArrivalNumber' => $this->templating->render('pack/datatableTruckArrivalNumber.html.twig', [
                'truckArrival' => $truckArrival
            ]),
            "trackingDelay" => $finalTrackingDelay,
            "limitTreatmentDate" => $pack->getTrackingDelay()
                ? $this->formatService->datetime($pack->getTrackingDelay()->getLimitTreatmentDate())
                : null,
        ];
    }

    public function dataRowGroupHistory(TrackingMovement $trackingMovement): array
    {
        return [
            'group' => $trackingMovement->getPackParent() ? ($this->formatService->pack($trackingMovement->getPackParent()) . '-' . $trackingMovement->getGroupIteration()) : '',
            'date' => $this->formatService->datetime($trackingMovement->getDatetime(), "", false, $this->security->getUser()),
            'type' => $this->formatService->status($trackingMovement->getType())
        ];
    }

    public function checkPackDataBeforeEdition(array $data): array
    {
        $quantity = $data['quantity'] ?? null;
        $weight = !empty($data['weight']) ? str_replace(",", ".", $data['weight']) : null;
        $volume = !empty($data['volume']) ? str_replace(",", ".", $data['volume']) : null;

        if ($quantity <= 0) {
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
                             array                  $data,
                             Pack                   $pack): void {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $projectRepository = $entityManager->getRepository(Project::class);

        $natureId = $data['nature'] ?? null;
        $projectId = $data['projects'] ?? null;
        $quantity = $data['quantity'] ?? null;
        $comment = $data['comment'] ?? null;
        $weight = !empty($data['weight']) ? str_replace(",", ".", $data['weight']) : null;
        $volume = !empty($data['volume']) ? str_replace(",", ".", $data['volume']) : null;

        $nature = $natureRepository->find($natureId);
        if (!empty($nature)) {
            $pack->setNature($nature);
        }

        $project = $projectRepository->findOneBy(["id" => $projectId]);

        $recordDate = new DateTime();
        $this->projectHistoryRecordService->changeProject($entityManager, $pack, $project, $recordDate);

        foreach($pack->getChildArticles() as $article) {
            $this->projectHistoryRecordService->changeProject($entityManager, $article, $project, $recordDate);
        }

        $pack
            ->setQuantity($quantity)
            ->setWeight($weight)
            ->setVolume($volume)
            ->setComment($comment);
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

                if($arrival->getTruckArrival() || $arrival->getTruckArrivalLines()->first()){
                    $arrivalHasLine = $arrival->getTruckArrivalLines()->first();
                    $truckArrival = $arrivalHasLine
                        ? $arrivalHasLine->getTruckArrival()
                        : $arrival->getTruckArrival();

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

    public function createPackWithCode(string $code): Pack
    {
        $pack = new Pack();
        $pack->setCode(str_replace("    ", " ", $code));
        return $pack;
    }

    public function persistPack(EntityManagerInterface $entityManager,
                                                       $packOrCode,
                                                       $quantity,
                                                       $natureId = null,
                                bool                   $onlyPack = false): Pack {
        $packRepository = $entityManager->getRepository(Pack::class);

        $codePack = $packOrCode instanceof Pack ? $packOrCode->getCode() : $packOrCode;

        $pack = ($packOrCode instanceof Pack)
            ? $packOrCode
            : $packRepository->findOneBy(['code' => $packOrCode]);

        if ($onlyPack && $pack && $pack->isGroup()) {
            throw new Exception(Pack::PACK_IS_GROUP);
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
                                        Reception              $reception = null): array
    {
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

        if (!$arrivage->getTruckArrivalLines()->isEmpty()) {
            $truckArrivalCreationDate = $arrivage->getTruckArrivalLines()->first()->getTruckArrival()->getCreationDate();

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
                $this->translation->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SERPARATOR. "Unité logistique non récupéré$titleSuffix",
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
                ["name" => 'nature', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Nature'), "searchable" => true],
                ["name" => 'code', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Unités logistiques'), "searchable" => true],
                ["name" => 'project', 'title' => $this->translation->translate('Référentiel', 'Projet', 'Projet', false), "searchable" => true],
                ["name" => 'lastMvtDate', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Date dernier mouvement'), "searchable" => true],
                ["name" => 'lastLocation', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Dernier emplacement'), "searchable" => true],
                ["name" => 'operator', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Opérateur'), "searchable" => true],
            ],
            [],
            $columnsVisible
        );
    }

    public function getPackListColumnVisibleConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getFieldModes(FieldModesController::PAGE_PACK_LIST) ?? Utilisateur::DEFAULT_PACK_LIST_FIELDS_MODES;

        return $this->fieldModesService->getArrayConfig(
            [
                ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true, "searchable" => true],
                ["name" => 'details', "title" => '<span class="fa fa-search"><span>', "className" => 'noVis', "orderable" => false],
                ['name' => 'cart', 'title' => '<span class="wii-icon wii-icon-cart"></span>', 'classname' => 'cart-row', "orderable" => false],
                ['name' => 'pairing', 'title' => '<span class="wii-icon wii-icon-pairing black"><span>', 'classname' => 'pairing-row'],
                ['name' => 'code', 'title' => $this->translationService->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Numéro d\'UL')],
                ['name' => 'nature', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Nature')],
                ['name' => 'quantity', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Quantité')],
                ['name' => 'project', 'title' => $this->translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Projet')],
                ['name' => 'lastMovementDate', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Date dernier mouvement')],
                ['name' => 'origin', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Issu de'), 'orderable' => false],
                ['name' => 'location', 'title' => $this->translationService->translate('Traçabilité', 'Général', 'Emplacement')],
                ['name' => 'receiptAssociation', 'title' => 'Association', 'classname' => 'noVis', 'orderable' => false],
                ['name' => 'truckArrivalNumber', 'title' => 'Arrivage camion', 'className' => 'noVis'],
                ['name' => 'trackingDelay', 'title' => 'Délai de traitement', 'className' => 'noVis'],
                ['name' => 'limitTreatmentDate', 'title' => 'Date limite de traitement', 'className' => 'noVis'],
            ],
            [],
            $columnsVisible
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
    ): array
    {

        $arrival = $pack->getArrivage();

        $dateAndHour = $arrival->getDate()
            ? $arrival->getDate()->format('d/m/Y H:i')
            : '';

        $truckArrivalLine = $arrival->getTruckArrivalLines()->first();
        $truckArrivalDateAndHour = $truckArrivalLine
            ? $this->formatService->datetime($truckArrivalLine->getTruckArrival()?->getCreationDate())
            : ($this->formatService->datetime($arrival->getTruckArrival()?->getCreationDate()) ?: '');

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

    public function generateTrackingDelayHtml(Pack $pack): ?string
    {
        $trackingDelayData = $this->formatTrackingDelayData($pack);

        return isset($trackingDelayData["color"]) && isset($trackingDelayData["delay"])
            ? "<span class='font-weight-bold' style='color: {$trackingDelayData["color"]}'>{$trackingDelayData["delay"]}</span>"
            : null;
    }

    /**
     * @return array{color?: string, delay?: string}
     */
    public function formatTrackingDelayData(Pack $pack): array {
        $packTrackingDelay = $pack->getTrackingDelay();
        $nature = $pack->getNature();
        $natureTrackingDelay = $nature?->getTrackingDelay();

        if(!$packTrackingDelay || !$natureTrackingDelay) {
            return [];
        }

        $trackingDelay = $this->dateTimeService->getWorkedPeriodBetweenDates($this->entityManager, $packTrackingDelay->getCalculatedAt(), new DateTime());

        $delayIsLate = false;
        $elapsedTime = $pack->getTrackingDelay()->getElapsedTime();
        $trackingDelayInSeconds = $packTrackingDelay->isTimerStopped()
            ? $elapsedTime
            : ($elapsedTime + floor($this->dateTimeService->convertDateIntervalToMilliseconds($trackingDelay) / 1000));

        $remainingTime = $natureTrackingDelay - $trackingDelayInSeconds;

        if($remainingTime < 0){
            $trackingDelayColor = $nature->getExceededDelayColor() ?? PackService::PACK_DEFAULT_TRACKING_DELAY_COLOR;
            $delayIsLate = true;
        } else {
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

        $remainingInterval = $this->dateTimeService->convertSecondsToDateInterval(abs($remainingTime));
        $formattedRemainingInterval = $this->dateTimeService->intervalToHourAndMinStr($remainingInterval);
        $strDelay = ($delayIsLate ? '-' : '') . $formattedRemainingInterval;

        return [
            "color" => $trackingDelayColor,
            "delay" => $strDelay,
        ];
    }

    public function getFormatedKeyboardPackGenerator(Iterator $packs): Iterator {
        $firstElement = [
            "id" => "new-item",
            "html" => "<div class='new-item-container'><span class='wii-icon wii-icon-plus'></span> <b>Nouvelle unité logistique</b></div>",
        ];

        $firstElement["highlighted"] = !$packs->valid();
        yield $firstElement;

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
}
