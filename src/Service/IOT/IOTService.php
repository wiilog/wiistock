<?php


namespace App\Service\IOT;


use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Handling;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorProfile;
use App\Entity\IOT\SensorWrapper;
use App\Entity\IOT\TriggerAction;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\LocationGroup;
use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;
use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Statut;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\Vehicle;
use App\Repository\ArticleRepository;
use App\Repository\IOT\SensorMessageRepository;
use App\Repository\PackRepository;
use App\Repository\StatutRepository;
use App\Service\DeliveryRequestService;
use App\Service\HttpService;
use App\Service\MailerService;
use App\Service\NotificationService;
use App\Service\UniqueNumberService;
use DateTimeZone;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class IOTService
{
    const ACS_EVENT = 'EVENT';
    const ACS_PRESENCE = 'PRESENCE';

    const INEO_SENS_ACS_TEMP_HYGRO = 'ACS-Switch-TEMP-HYGRO';
    const INEO_SENS_ACS_TEMP = 'ACS-Switch-TEMP';
    const INEO_SENS_ACS_HYGRO = 'ACS-Switch-HYGRO';
    const INEO_SENS_ACS_BTN = 'acs-switch-bouton';
    const INEO_SENS_GPS = 'trk-tracer-gps-new';
    const INEO_INS_EXTENDER = 'Ins-Extender';
    const SYMES_ACTION_SINGLE = 'symes-action-single';
    const SYMES_ACTION_MULTI = 'symes-action-multi';
    const KOOVEA_TAG = 'Tag température Koovea';
    const KOOVEA_HUB = 'Hub GPS Koovea';
    const DEMO_TEMPERATURE = 'demo-temperature';
    const DEMO_ACTION = 'demo-action';

    const PROFILE_TO_MAX_TRIGGERS = [
        self::INEO_SENS_ACS_TEMP => 2,
        self::INEO_SENS_ACS_HYGRO => 2,
        self::INEO_SENS_ACS_TEMP_HYGRO => 4,
        self::INEO_SENS_GPS => 1,
        self::INEO_SENS_ACS_BTN => 1,
        self::SYMES_ACTION_MULTI => 4,
        self::SYMES_ACTION_SINGLE => 1,
        self::KOOVEA_TAG => 1,
        self::KOOVEA_HUB => 1,
        self::INEO_INS_EXTENDER => 0,
    ];

    const PROFILE_TO_TYPE = [
        self::INEO_SENS_ACS_TEMP => Sensor::TEMPERATURE,
        self::INEO_SENS_ACS_TEMP_HYGRO => Sensor::TEMPERATURE_HYGROMETRY,
        self::INEO_SENS_ACS_HYGRO => Sensor::HYGROMETRY,
        self::KOOVEA_TAG => Sensor::TEMPERATURE,
        self::KOOVEA_HUB => Sensor::GPS,
        self::INEO_SENS_GPS => Sensor::GPS,
        self::INEO_SENS_ACS_BTN => Sensor::ACTION,
        self::SYMES_ACTION_MULTI => Sensor::ACTION,
        self::SYMES_ACTION_SINGLE => Sensor::ACTION,
        self::DEMO_ACTION => Sensor::ACTION,
        self::DEMO_TEMPERATURE => Sensor::TEMPERATURE,
        self::INEO_INS_EXTENDER => Sensor::EXTENDER,
    ];

    const PROFILE_TO_FREQUENCY = [
        self::INEO_SENS_ACS_TEMP => 'x minutes',
        self::INEO_SENS_ACS_TEMP_HYGRO =>  'x minutes',
        self::INEO_SENS_ACS_HYGRO =>  'x minutes',
        self::INEO_SENS_GPS => 'x minutes',
        self::KOOVEA_TAG => 'x minutes',
        self::KOOVEA_HUB => 'x minutes',
        self::INEO_SENS_ACS_BTN => 'à l\'action',
        self::SYMES_ACTION_SINGLE => 'à l\'action',
        self::SYMES_ACTION_MULTI => 'à l\'action',
        self::INEO_INS_EXTENDER => 'au message reçu',
    ];

    const DATA_TYPE_ERROR = 0;
    const DATA_TYPE_TEMPERATURE = 1;
    const DATA_TYPE_HYGROMETRY = 2;
    const DATA_TYPE_ACTION = 3;
    const DATA_TYPE_GPS = 4;
    const DATA_TYPE_SENSOR_CLOVER_MAC = 5;
    const DATA_TYPE_PAYLOAD = 6;

    const DATA_TYPE = [
        self::DATA_TYPE_TEMPERATURE => 'Température',
        self::DATA_TYPE_HYGROMETRY => 'Hygrométrie',
        self::DATA_TYPE_ACTION => 'Action',
        self::DATA_TYPE_GPS => 'GPS',
    ];

    const DATA_TYPE_TO_UNIT = [
        self::DATA_TYPE_TEMPERATURE => '°C',
        self::DATA_TYPE_HYGROMETRY => '%',
    ];

    #[Required]
    public DeliveryRequestService $demandeLivraisonService;

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public AlertService $alertService;

    #[Required]
    public NotificationService $notificationService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public HttpService $client;

    public function onMessageReceived(array $frame, EntityManagerInterface $entityManager, bool $local = false): void {
        $messages = $this->parseAndCreateMessage($frame, $entityManager, $local);
        foreach ($messages as $message) {
            if($message){
                $this->linkWithSubEntities($entityManager, $message);
                $entityManager->flush();
                $this->treatTriggers($entityManager, $message);
                $entityManager->flush();
            }
        }
    }

    private function treatTriggers(EntityManagerInterface $entityManager, SensorMessage $sensorMessage) {
        $sensor = $sensorMessage->getSensor();
        $wrapper = $sensor->getAvailableSensorWrapper();
        if ($wrapper) {
            foreach ($wrapper->getTriggerActions() as $triggerAction) {
                $type = $sensorMessage->getContentType();
                switch ($type) {
                    case IOTService::DATA_TYPE_ACTION:
                        $this->treatActionTrigger($wrapper, $triggerAction, $sensorMessage, $entityManager);
                        break;
                    case IOTService::DATA_TYPE_TEMPERATURE:
                        $this->treatDataTrigger($triggerAction, $sensorMessage, $entityManager, $wrapper, 'temperature');
                        break;
                    case IOTService::DATA_TYPE_HYGROMETRY:
                        $this->treatDataTrigger($triggerAction, $sensorMessage, $entityManager, $wrapper, 'hygrometry');
                        break;
                    default:
                        break;
                }
            }
        }

        if (!$entityManager->isOpen()) {
            $entityManager = $entityManager->create(
                $entityManager->getConnection(),
                $entityManager->getConfiguration()
            );
        }
        $entityManager->flush();
    }

    private function treatDataTrigger(TriggerAction          $triggerAction,
                                      SensorMessage          $sensorMessage,
                                      EntityManagerInterface $entityManager,
                                      SensorWrapper          $wrapper,
                                      string                 $dataType): void {

        $config = $triggerAction->getConfig();

        if(!isset($config[$dataType])){
            return;
        }
        $dataThreshold = floatval($config[$dataType] );
        $message = floatval($sensorMessage->getContent());

        $temperatureThresholdType = $config['limit'];

        $needsTrigger = $temperatureThresholdType === TriggerAction::LOWER
            ? $dataThreshold >= $message
            : $dataThreshold <= $message;
        $triggerAction->setLastTrigger(new DateTime('now'));
        if ($needsTrigger) {
            if ($triggerAction->getRequestTemplate()) {
                $this->treatRequestTemplateTriggerType($triggerAction->getRequestTemplate(), $entityManager, $wrapper);
            } else if ($triggerAction->getAlertTemplate()) {
                $this->treatAlertTemplateTriggerType($triggerAction->getAlertTemplate(), $sensorMessage, $entityManager);
            }
            $this->treatTrackLinksOnTrigger($entityManager, $wrapper, $temperatureThresholdType);
        }
    }

    private function treatActionTrigger(SensorWrapper $wrapper, TriggerAction $triggerAction, SensorMessage $sensorMessage, EntityManagerInterface $entityManager) {
        $needsTrigger = $sensorMessage->getEvent() === self::ACS_EVENT;
        if ($needsTrigger && $sensorMessage->getSensor()->getProfile()->getName() === IOTService::SYMES_ACTION_MULTI) {
            $button = intval(substr($sensorMessage->getContent(), 7, 1)); //EVENT (2)
            $config = $triggerAction->getConfig();
            $wanted = intval($config['buttonIndex']);
            $needsTrigger = ($button === $wanted);
        }
        dump("ON PASSE ICI");
        dump($needsTrigger);
        dump($triggerAction);
        if ($needsTrigger) {
            if ($triggerAction->getRequestTemplate()) {
                $this->treatRequestTemplateTriggerType($triggerAction->getRequestTemplate(), $entityManager, $wrapper);
            } else if ($triggerAction->getAlertTemplate()) {
                $this->treatAlertTemplateTriggerType($triggerAction->getAlertTemplate(), $sensorMessage, $entityManager);
            }
        }
    }

    private function treatRequestTemplateTriggerType(RequestTemplate $requestTemplate, EntityManagerInterface $entityManager, SensorWrapper $wrapper) {
        $statutRepository = $entityManager->getRepository(Statut::class);

        if ($requestTemplate instanceof DeliveryRequestTemplate) {
            $request = $this->cleanCreateDeliveryRequest($statutRepository, $entityManager, $wrapper, $requestTemplate);

            $this->uniqueNumberService->createWithRetry(
                $entityManager,
                Demande::NUMBER_PREFIX,
                Demande::class,
                UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT,
                function (string $number) use ($request, $entityManager) {
                    $request->setNumero($number);
                    $entityManager->persist($request);
                    $entityManager->flush();
                }
            );

            $valid = true;
            foreach ($request->getReferenceLines() as $ligneArticle) {
                $reference = $ligneArticle->getReference();
                if ($reference->getQuantiteDisponible() < $ligneArticle->getQuantityToPick()) {
                    $valid = false;
                    break;
                }
            }
            if ($valid) {
                $this->demandeLivraisonService->validateDLAfterCheck($entityManager, $request, false, true);
            }

            if (!$entityManager->isOpen()) {
                $entityManager = $entityManager->create(
                    $entityManager->getConnection(),
                    $entityManager->getConfiguration()
                );
            }

            $entityManager->flush();
        } else if ($requestTemplate instanceof CollectRequestTemplate) {
            $request = $this->cleanCreateCollectRequest($statutRepository, $entityManager, $wrapper, $requestTemplate);
            $entityManager->persist($request);
            $entityManager->flush();
        } else if ($requestTemplate instanceof HandlingRequestTemplate) {
            $request = $this->cleanCreateHandlingRequest($wrapper, $requestTemplate);

            $this->uniqueNumberService->createWithRetry(
                $entityManager,
                Handling::NUMBER_PREFIX,
                Handling::class,
                UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT,
                function (string $number) use ($request, $entityManager) {
                    $request->setNumber($number);
                    $entityManager->persist($request);
                    $entityManager->flush();
                }
            );
        }
    }

    private function cleanCreateHandlingRequest(SensorWrapper $sensorWrapper,
                                                HandlingRequestTemplate $requestTemplate): Handling {
        $handling = new Handling();
        $date = new DateTime('now');

        $desiredDate = clone $date;
        $desiredDate = $desiredDate->add(new \DateInterval('PT' . $requestTemplate->getDelay() . 'H'));

        $handling
            ->setFreeFields($requestTemplate->getFreeFields())
            ->setCarriedOutOperationCount($requestTemplate->getCarriedOutOperationCount())
            ->setSource($requestTemplate->getSource())
            ->setEmergency($requestTemplate->getEmergency())
            ->setDestination($requestTemplate->getDestination())
            ->setType($requestTemplate->getRequestType())
            ->setCreationDate($date)
            ->setTriggeringSensorWrapper($sensorWrapper)
            ->setStatus($requestTemplate->getRequestStatus())
            ->setComment($requestTemplate->getComment())
            ->setAttachments($requestTemplate->getAttachments())
            ->setSubject($requestTemplate->getSubject())
            ->setDesiredDate($desiredDate);

        return $handling;

    }

    private function cleanCreateDeliveryRequest(StatutRepository $statutRepository,
                                                EntityManagerInterface $entityManager,
                                                SensorWrapper $wrapper,
                                                DeliveryRequestTemplate $requestTemplate): Demande {
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
        $date = new DateTime('now');

        $request = new Demande();
        $request
            ->setStatut($statut)
            ->setCreatedAt($date)
            ->setCommentaire($requestTemplate->getComment())
            ->setTriggeringSensorWrapper($wrapper)
            ->setType($requestTemplate->getRequestType())
            ->setDestination($requestTemplate->getDestination())
            ->setFreeFields($requestTemplate->getFreeFields());

        foreach ($requestTemplate->getLines() as $requestTemplateLine) {
            $ligneArticle = new DeliveryRequestReferenceLine();
            $ligneArticle
                ->setReference($requestTemplateLine->getReference())
                ->setRequest($request)
                ->setQuantityToPick($requestTemplateLine->getQuantityToTake()); // protection contre quantités négatives
            $entityManager->persist($ligneArticle);
            $request->addReferenceLine($ligneArticle);
        }
        return $request;
    }

    private function cleanCreateCollectRequest(StatutRepository $statutRepository,
                                                EntityManagerInterface $entityManager,
                                                SensorWrapper $wrapper,
                                                CollectRequestTemplate $requestTemplate): Collecte {
        $date = new DateTime('now');
        $numero = 'C-' . $date->format('YmdHis');
        $status = $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_BROUILLON);

        $request = new Collecte();
        $request
            ->setTriggeringSensorWrapper($wrapper)
            ->setNumero($numero)
            ->setDate($date)
            ->setFreeFields($requestTemplate->getFreeFields())
            ->setType($requestTemplate->getRequestType())
            ->setStatut($status)
            ->setPointCollecte($requestTemplate->getCollectPoint())
            ->setObjet($requestTemplate->getSubject())
            ->setCommentaire($requestTemplate->getComment())
            ->setstockOrDestruct($requestTemplate->getDestination());
        $entityManager->persist($request);
        $entityManager->flush();

        foreach ($requestTemplate->getLines() as $requestTemplateLine) {
            $ligneArticle = new CollecteReference();
            $ligneArticle
                ->setReferenceArticle($requestTemplateLine->getReference())
                ->setCollecte($request)
                ->setQuantite($requestTemplateLine->getQuantityToTake()); // protection contre quantités négatives
            $entityManager->persist($ligneArticle);
            $request->addCollecteReference($ligneArticle);
        }
        $ordreCollecte = $this->cleanCreateCollectOrder($statutRepository, $request, $entityManager);
        $entityManager->flush();

        if ($ordreCollecte->getDemandeCollecte()->getType()->isNotificationsEnabled()) {
            $this->notificationService->toTreat($ordreCollecte);
        }
        return $request;
    }

    private function cleanCreateCollectOrder(StatutRepository $statutRepository, Collecte $demandeCollecte, EntityManagerInterface $entityManager) {

        $statut = $statutRepository
            ->findOneByCategorieNameAndStatutCode(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_A_TRAITER);
        $ordreCollecte = new OrdreCollecte();
        $date = new DateTime('now');
        $ordreCollecte
            ->setDate($date)
            ->setNumero('C-' . $date->format('YmdHis'))
            ->setStatut($statut)
            ->setDemandeCollecte($demandeCollecte);
        foreach ($demandeCollecte->getArticles() as $article) {
            $ordreCollecte->addArticle($article);
        }
        foreach ($demandeCollecte->getCollecteReferences() as $collecteReference) {
            $ordreCollecteReference = new OrdreCollecteReference();
            $ordreCollecteReference
                ->setOrdreCollecte($ordreCollecte)
                ->setQuantite($collecteReference->getQuantite())
                ->setReferenceArticle($collecteReference->getReferenceArticle());
            $entityManager->persist($ordreCollecteReference);
            $ordreCollecte->addOrdreCollecteReference($ordreCollecteReference);
        }

        $entityManager->persist($ordreCollecte);


        // on modifie statut + date validation de la demande
        $demandeCollecte
            ->setStatut(
                $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER)
            )
            ->setValidationDate($date);

        return $ordreCollecte;
    }

    private function treatAlertTemplateTriggerType(AlertTemplate $template, SensorMessage $message, EntityManagerInterface $entityManager) {
        $this->alertService->trigger($template, $message, $entityManager);
    }

    private function parseAndCreateMessage(array $message, EntityManagerInterface $entityManager, bool $local): array {
        $deviceRepository = $entityManager->getRepository(Sensor::class);

        $deviceCode = $message['metadata']["network"]["lora"]["devEUI"] ?? null;

        $device = $deviceCode
            ? $deviceRepository->findOneBy([
                'code' => $deviceCode,
            ])
            : null;

        if (!($device instanceof Sensor)) {
            $deviceCode = $message['sensorCloverMac'] ?? null;

            $device = $deviceCode
                ? $deviceRepository->findOneBy([
                    'cloverMac' => $deviceCode,
                ])
                : null;
        }

        if (!($device instanceof Sensor)) {
            return [];
        }

        $profile =  $device->getProfile()->getName();

        $frameIsValid = $this->validateFrame($profile, $message);
        if(!$frameIsValid){
            return [];
        }

        $newBattery = $this->extractBatteryLevelFromMessage($message, $profile );
        $wrapper = $device->getAvailableSensorWrapper();
        if ($newBattery > -1) {
            $device->setBattery($newBattery);
            if ($newBattery < 10 && $wrapper && $wrapper->getManager()) {
                $this->mailerService->sendMail(
                    'FOLLOW GT // Batterie capteur faible',
                    $this->templating->render('mails/contents/iot/mailLowBattery.html.twig', [
                        'sensorCode' => $device->getCode(),
                        'sensorName' => $wrapper->getName(),
                    ]),
                    $wrapper->getManager()
                );
            }
        }
        $entityManager->flush();

        $mainDatas = $this->extractMainDataFromConfig($message, $device->getProfile()->getName());
        if ( $device->getType()->getLabel() === Sensor::EXTENDER
            && array_key_exists(self::DATA_TYPE_PAYLOAD, $mainDatas)
            && array_key_exists(self::DATA_TYPE_SENSOR_CLOVER_MAC, $mainDatas)) {
            $fakeFrame = [
                'sensorCloverMac' => $mainDatas[IOTService::DATA_TYPE_SENSOR_CLOVER_MAC],
                'value' => [
                    'payload' => $mainDatas[IOTService::DATA_TYPE_PAYLOAD],
                ],
                'timestamp' => $message['timestamp'] ?? 'now',
                'extenderPayload' => $message
            ];
            $this->onMessageReceived($fakeFrame, $entityManager);
            return [];
        }

        $messageDate = new DateTime($message['timestamp'], $local ? null : new DateTimeZone("UTC"));
        if (!$local) {
            $messageDate->setTimezone(new DateTimeZone('Europe/Paris'));
        }

        $messages = Stream::from($mainDatas)
            ->map(function ($mainData, $type) use ($message, $messageDate, $device, $entityManager) :SensorMessage {
                $received = new SensorMessage();
                $received
                    ->setPayload($message)
                    ->setDate($messageDate)
                    ->setContent($mainData)
                    ->setContentType($type)
                    ->setEvent($this->extractEventTypeFromMessage($message, $device->getProfile()->getName()))
                    ->setLinkedSensorLastMessage($device)
                    ->setSensor($device);

                return $received;
            })
            ->toArray();

        foreach ($messages as $message) {
            $entityManager->persist($message);
        }

        return $messages;
    }

    public function linkWithSubEntities(EntityManagerInterface $entityManager,
                                        SensorMessage $sensorMessage) {
        $packRepository = $entityManager->getRepository(Pack::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $sensor = $sensorMessage->getSensor();
        $wrapper = $sensor->getAvailableSensorWrapper();
        if ($wrapper) {
            foreach ($wrapper->getPairings() as $pairing) {
                if ($pairing->isActive()) {
                    if($pairing->getEnd() && $pairing->getEnd() < new DateTime()) {
                        $pairing->setActive(false);
                        continue;
                    }

                    $pairing->addSensorMessage($sensorMessage);
                    $entity = $pairing->getEntity();
                    if ($entity instanceof LocationGroup) {
                        $this->treatAddMessageLocationGroup($entity, $sensorMessage, $articleRepository, $packRepository);
                    } else if ($entity instanceof Emplacement) {
                        $this->treatAddMessageLocation($entity, $sensorMessage, $articleRepository, $packRepository);
                    } else if ($entity instanceof Pack) {
                        $this->treatAddMessagePack($entity, $sensorMessage);
                    } else if ($entity instanceof Article) {
                        $this->treatAddMessageArticle($entity, $sensorMessage);
                    } else if ($entity instanceof Preparation) {
                        $this->treatAddMessageOrdrePrepa($entity, $sensorMessage);
                    } else if ($entity instanceof OrdreCollecte) {
                        $this->treatAddMessageOrdreCollecte($entity, $sensorMessage);
                    } else if ($entity instanceof Vehicle) {
                        $this->treatAddMessageForVehicle($entity, $sensorMessage, $articleRepository, $packRepository);
                    }
                }
            }
        }
    }

    private function treatAddMessageForVehicle(Vehicle $vehicle,
                                               SensorMessage $sensorMessage,
                                               ArticleRepository $articleRepository,
                                               PackRepository $packRepository) {
        $vehicle->addSensorMessage($sensorMessage);
        foreach ($vehicle->getLocations() as $location) {
            $this->treatAddMessageLocation($location, $sensorMessage, $articleRepository, $packRepository);
        }
    }

    private function treatAddMessageLocationGroup(LocationGroup $locationGroup,
                                                  SensorMessage $sensorMessage,
                                                  ArticleRepository $articleRepository,
                                                  PackRepository $packRepository) {
        $locationGroup->addSensorMessage($sensorMessage);
        foreach ($locationGroup->getLocations() as $location) {
            $this->treatAddMessageLocation($location, $sensorMessage, $articleRepository, $packRepository);
        }
    }

    private function treatAddMessageLocation(Emplacement $location,
                                             SensorMessage $sensorMessage,
                                             ArticleRepository $articleRepository,
                                             PackRepository $packRepository) {
        $location->addSensorMessage($sensorMessage);
        $packs = $packRepository->getCurrentPackOnLocations(
            [$location->getId()],
            [
                'isCount' => false,
                'field' => 'pack',
            ]
        );

        $articles = $articleRepository->findArticlesOnLocation($location);

        foreach ($articles as $article) {
            $this->treatAddMessageArticle($article, $sensorMessage);
        }

        foreach ($packs as $pack) {
            $this->treatAddMessagePack($pack, $sensorMessage);
        }
    }

    private function treatAddMessagePack(Pack $pack, SensorMessage $sensorMessage) {
        $pack->addSensorMessage($sensorMessage);
    }

    private function treatAddMessageArticle(Article $article, SensorMessage $sensorMessage) {
        $article->addSensorMessage($sensorMessage);
    }

    private function treatAddMessageDeliveryRequest(Demande $request, SensorMessage $sensorMessage) {
        $request->addSensorMessage($sensorMessage);
    }

    private function treatAddMessageCollectRequest(Collecte $request, SensorMessage $sensorMessage) {
        $request->addSensorMessage($sensorMessage);
    }

    private function treatAddMessageOrdrePrepa(Preparation $preparation, SensorMessage $sensorMessage) {
        $preparation->addSensorMessage($sensorMessage);
        $this->treatAddMessageDeliveryRequest($preparation->getDemande(), $sensorMessage);
        foreach ($preparation->getArticleLines() as $article) {
            $this->treatAddMessageArticle($article, $sensorMessage);
        }
    }

    private function treatAddMessageOrdreCollecte(OrdreCollecte $ordreCollecte, SensorMessage $sensorMessage) {
        $ordreCollecte->addSensorMessage($sensorMessage);
        $this->treatAddMessageCollectRequest($ordreCollecte->getDemandeCollecte(), $sensorMessage);
        foreach ($ordreCollecte->getArticles() as $article) {
            $this->treatAddMessageArticle($article, $sensorMessage);
        }
    }

    public function extractMainDataFromConfig(array $config, string $profile): array {
        switch ($profile) {
            case IOTService::INEO_SENS_ACS_TEMP_HYGRO:
                $hexTemperature = substr($config['value']['payload'], 6, 2);
                $temperature = $this->convertHexToSignedInt($hexTemperature);
                $hexHygrometry = substr($config['value']['payload'], 66, 2);
                $hygrometry = $this->convertHexToSignedInt($hexHygrometry);
                return [
                    self::DATA_TYPE_TEMPERATURE => $temperature,
                    self::DATA_TYPE_HYGROMETRY => $hygrometry,
                ];
            case IOTService::INEO_SENS_ACS_TEMP:
                $hexTemperature = substr($config['value']['payload'], 6, 2);
                $temperature = $this->convertHexToSignedInt($hexTemperature);
                return [self::DATA_TYPE_TEMPERATURE => $temperature,];
            case IOTService::INEO_SENS_ACS_HYGRO:
                $hexHygrometry = substr($config['value']['payload'], 66, 2);
                $hygrometry = $this->convertHexToSignedInt($hexHygrometry);
                return [self::DATA_TYPE_HYGROMETRY => $hygrometry,];
            case IOTService::KOOVEA_TAG:
                return [self::DATA_TYPE_TEMPERATURE => $config['value']];
            case IOTService::KOOVEA_HUB:
                return [self::DATA_TYPE_GPS => $config['value']];
            case IOTService::INEO_SENS_ACS_BTN:
                return [self::DATA_TYPE_ACTION => $this->extractEventTypeFromMessage($config, $profile)];
            case IOTService::SYMES_ACTION_MULTI:
            case IOTService::SYMES_ACTION_SINGLE:
                // TODO WIIS-10287 check $config['payload_cleartext']
                if (isset($config['value']['payload'])) {
                    // TODO WIIS-10287 check $config['payload_cleartext']
                    $value = hexdec(substr($config['value']['payload'], 0, 2));
                    $event =  $value & ~($value >> 3 << 3);
                    return [self::DATA_TYPE_ACTION => $event === 0 ? self::ACS_PRESENCE : (self::ACS_EVENT . " (" . $event . ")")];
                }
                break;
            case IOTService::DEMO_TEMPERATURE:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return [self::DATA_TYPE_TEMPERATURE => $frame['jcd_temperature']];
                }
                break;
            case IOTService::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    if (isset($frame['LATITUDE']) && isset($frame['LONGITUDE'])) {
                        $data = $frame['LATITUDE'] . ',' . $frame['LONGITUDE'];
                    } else {
                        $data = '-1,-1';
                    }
                    return [self::DATA_TYPE_GPS => $data];
                }
                break;
            case IOTService::INEO_INS_EXTENDER:
                $frame = $config['value']['payload'];
                if (str_starts_with($frame, '49')) {
                    return [
                        // Current device temperature. Range is from 0 to 250, where 0 represents -100°C, 250 represent 150°C (thus 0°C will be 100).
                        self::DATA_TYPE_TEMPERATURE => hexdec(substr($config['value']['payload'], 38, 2)) - 100,
                    ];
                } else if (str_starts_with($frame, '12')) {
                    $payloadSizeHexa = substr($frame, 12, 2);
                    // Convert hexa to decimal and multiply by 2 to get the number of bytes plus 2 for the header
                    $payloadSize = hexdec($payloadSizeHexa)*2+2;
                    return [
                        self::DATA_TYPE_SENSOR_CLOVER_MAC => substr($frame, 2, 8),
                        self::DATA_TYPE_PAYLOAD => substr($frame, 14, $payloadSize),
                    ];
                } else {
                    return [];
                }
        }
        return [self::DATA_TYPE_ERROR => 'Donnée principale non trouvée'];
    }

    public function extractEventTypeFromMessage(array $config, string $profile) {
        switch ($profile) {
            case IOTService::KOOVEA_TAG:
            case IOTService::KOOVEA_HUB:
                return $config['event'];
            case IOTService::INEO_SENS_ACS_BTN:
            case IOTService::INEO_SENS_ACS_TEMP:
            case IOTService::INEO_SENS_ACS_TEMP_HYGRO:
            case IOTService::INEO_SENS_ACS_HYGRO:
                return 'PERIODIC_EVENT';
            case IOTService::DEMO_TEMPERATURE:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_msg_type'];
                }
                break;
            case IOTService::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    if (isset($frame['NEW_EVT_TYPE'])) {
                        return $frame['NEW_EVT_TYPE'];
                    } else if ($frame['NEW_BATT']) {
                        return 'BATTERY_INFO';
                    }
                }
                break;
            case IOTService::SYMES_ACTION_SINGLE:
            case IOTService::SYMES_ACTION_MULTI:
            // TODO WIIS-10287 check $config['payload_cleartext']
                if (isset($config['value']['payload'])) {
                    // TODO WIIS-10287 check $config['payload_cleartext']
                    $value = hexdec(substr($config['value']['payload'], 0, 2));
                    $event =  $value & ~($value >> 3 << 3);
                    return $event === 0 ? self::ACS_PRESENCE : self::ACS_EVENT;
                }
                break;
            case IOTService::INEO_INS_EXTENDER:
                $frame = $config['value']['payload'];
                if (str_starts_with($frame, '49')) {
                    return self::ACS_PRESENCE;
                }
                break;
        }
        return 'Évenement non trouvé';
    }

    public function extractBatteryLevelFromMessage(array $config, string $profile) {
        switch ($profile) {
            case IOTService::KOOVEA_TAG:
            case IOTService::KOOVEA_HUB:
                return -1;
            case IOTService::INEO_SENS_ACS_HYGRO:
            case IOTService::INEO_SENS_ACS_TEMP:
            case IOTService::INEO_SENS_ACS_TEMP_HYGRO:
                return 100 - hexdec(substr($config['value']['payload'], 10, 2));
            case IOTService::INEO_SENS_ACS_BTN:
            case IOTService::DEMO_TEMPERATURE:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_battery_level'];
                }
                break;
            case IOTService::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['NEW_BATT'] ?? -1;
                }
                break;
            case IOTService::SYMES_ACTION_MULTI:
            case IOTService::SYMES_ACTION_SINGLE:
            // TODO WIIS-10287 check $config['payload_cleartext']
                if (isset($config['value']['payload'])) {
                    // TODO WIIS-10287 check $config['payload_cleartext']
                    $tensionBites = substr($config['value']['payload'], 20, 2);
                    $level = hexdec($tensionBites) >> 1;
                    $minVoltage = 2400;
                    $maxVoltage = 3700;
                    $incertitudeLevel = 10;
                    $currentVoltage = $level * $incertitudeLevel + $minVoltage;
                    return (($currentVoltage - $minVoltage) / ($maxVoltage - $minVoltage)) * 100;
                }
                break;
            case IOTService::INEO_INS_EXTENDER:
                $frame = $config['value']['payload'];
                if (str_starts_with($frame, '49')) {
                    return 100 - hexdec(substr($config['value']['payload'], 24, 2));
                } else {
                    return -1;
                }
        }
        return -1;
    }

    public static function getEntityCodeFromEntity(?PairedEntity $pairedEntity): ?string {
        if($pairedEntity instanceof Emplacement) {
            $code = Sensor::LOCATION;
        } else if($pairedEntity instanceof LocationGroup) {
            $code = Sensor::LOCATION_GROUP;
        } else if($pairedEntity instanceof Article) {
            $code = Sensor::ARTICLE;
        } else if($pairedEntity instanceof Pack) {
            $code = Sensor::PACK;
        } else if($pairedEntity instanceof Vehicle) {
            $code = Sensor::VEHICLE;
        } else if($pairedEntity instanceof Preparation) {
            $code = Sensor::PREPARATION;
        } else if($pairedEntity instanceof OrdreCollecte) {
            $code = Sensor::COLLECT_ORDER;
        } else if($pairedEntity instanceof Collecte) {
            $code = Sensor::COLLECT_REQUEST;
        } else if($pairedEntity instanceof Demande) {
            $code = Sensor::DELIVERY_REQUEST;
        }
        return $code ?? null;
    }

    public function getEntityClassFromCode(?string $code): ?string {
        $association = [
            Sensor::LOCATION => Emplacement::class,
            Sensor::LOCATION_GROUP => LocationGroup::class,
            Sensor::ARTICLE => Article::class,
            Sensor::PACK => Pack::class,
            Sensor::DELIVERY_REQUEST => Demande::class,
            Sensor::COLLECT_ORDER => OrdreCollecte::class,
            Sensor::COLLECT_REQUEST => Collecte::class,
            Sensor::PREPARATION => Preparation::class,
        ];
        return $association[$code] ?? null;
    }

    public function runKooveaJOB(EntityManagerInterface $entityManager, string $type) {
        $auth = $this->getAuthenticationTokens();
        if ($auth) {
            if ($type === self::KOOVEA_TAG) {
                $this->getTagsTemperatures($entityManager, $auth);
            } else if ($type === self::KOOVEA_HUB) {
                $this->getHubsPositions($entityManager, $auth);
            }
        }
    }

    private function getAuthenticationTokens() {
        return $this->client->requestUsingGuzzle('https://api.koovea.fr/api/login', 'POST', [
            "email" => "gestionwiilog@gmail.com",
            "hashed" => false,
            "password" => $_SERVER['KOOVEA_PASS'],
        ]);
    }

    private function getTagsTemperatures(EntityManagerInterface $entityManager, array $auth)
    {
        $sensorProfiles = $entityManager->getRepository(SensorProfile::class);
        $sensors = $entityManager->getRepository(Sensor::class);

        $kooveaTagProfile = $sensorProfiles->findOneBy(['name' => IOTService::KOOVEA_TAG]);
        $tags = $sensors->findBy([
            'profile' => $kooveaTagProfile,
        ]);

        $tags = Stream::from($tags)
            ->map(fn(Sensor $tag) => $tag->getCode())
            ->toArray();

        if ($auth) {
            $token = $auth['data']['authToken'];
            $userID = $auth['data']['userId'];
            $receivedStartDate = new DateTime('5 minutes ago');
            $receivedEndDate = new DateTime('now');

            $temperatures = $this->client->requestUsingGuzzle('https://api.koovea.fr/api/v2/getTagTemperatures', 'POST', [
                "authToken" => $token,
                "receivedStartDate" => $receivedStartDate->format('Y/m/d H:i:s'),
                "receivedEndDate" => $receivedEndDate->format('Y/m/d H:i:s'),
                "tagIds" => $tags,
                "userId" => $userID,
            ]);

            if ($temperatures && $temperatures['apiCode'] === 200) {
                $tagsMetrics = $temperatures["tags"];

                foreach ($tagsMetrics as $tagMetric) {
                    foreach ($tagMetric as $code => $tagInfos) {
                        foreach ($tagInfos['temperatures'] ?? [] as $temperature) {
                            $dateReceived = $temperature['date'];
                            $value = $temperature['value'];

                            $fakeFrame = [
                                'metadata' => [
                                    "network" => [
                                        "lora" => [
                                            "devEUI" => $code,
                                        ],
                                    ],
                                ],
                                'profile' => IOTService::KOOVEA_TAG,
                                'device_id' => $code,
                                'timestamp' => $dateReceived,
                                'value' => $value,
                                'event' => IOTService::ACS_PRESENCE,
                            ];

                            $this->onMessageReceived($fakeFrame, $entityManager, true);
                        }
                    }
                }
            }
        }
    }

    private function getHubsPositions(EntityManagerInterface $entityManager, array $auth)
    {
        $sensorProfiles = $entityManager->getRepository(SensorProfile::class);
        $sensors = $entityManager->getRepository(Sensor::class);
        $sensorMessages = $entityManager->getRepository(SensorMessage::class);

        $kooveaHubProfile = $sensorProfiles->findOneBy(['name' => IOTService::KOOVEA_HUB]);
        $hubs = $sensors->findBy([
            'profile' => $kooveaHubProfile,
        ]);

        $entities = Stream::from($hubs)
            ->keymap(fn(Sensor $hub) => [$hub->getCode(), $hub])
            ->toArray();

        $hubs = Stream::from($hubs)
            ->map(fn(Sensor $hub) => $hub->getCode())
            ->toArray();

        if ($auth) {
            $token = $auth['data']['authToken'];
            $userID = $auth['data']['userId'];
            $receivedStartDate = new DateTime('1 minute ago');
            $receivedEndDate = new DateTime('now');

            $positions = $this->client->requestUsingGuzzle('https://api.koovea.fr/api/v2/getHubLocalizations', 'POST', [
                "authToken" => $token,
                "receivedStartDate" => $receivedStartDate->format('Y/m/d H:i:s'),
                "receivedEndDate" => $receivedEndDate->format('Y/m/d H:i:s'),
                "imeis" => $hubs,
                "userId" => $userID,
            ]);
            if ($positions && $positions['apiCode'] === 200) {
                $hubPositions = $positions["hubs"];

                foreach ($hubPositions as $code => $positions) {
                    foreach ($positions['localizations'] ?? [] as $localization) {
                        $dateReceived = $localization['date'];
                        $value = $localization['latitude'] . ',' . $localization['longitude'];

                        $fakeFrame = [
                            'profile' => IOTService::KOOVEA_HUB,
                            'device_id' => $code,
                            'timestamp' => $dateReceived,
                            'value' => $value,
                            'event' => IOTService::ACS_PRESENCE,
                        ];
                        $this->insertGPSFrameFromKoovea($entities, $fakeFrame, $sensorMessages, $entityManager);
                    }
                }
            }
            $entityManager->flush();
        }
    }

    public function insertGPSFrameFromKoovea(array $entities, array $frame, SensorMessageRepository $sensorMessageRepository, EntityManagerInterface $entityManager) {
        /** @var Sensor $linkedDevice */
        $linkedDevice = $entities[$frame['device_id']];
        $linked = [];


        $wrapper = $linkedDevice->getAvailableSensorWrapper();
        $pairing = $wrapper?->getActivePairing();
        if ($pairing) {
            $packRepository = $entityManager->getRepository(Pack::class);

            /** @var Vehicle $vehicle */
            $vehicle = $pairing->getEntity();
            $locations = $vehicle->getLocations();

            $locations = Stream::from($locations)
                ->map(fn(Emplacement $location) => $location->getId())
                ->toArray();

            /*
             * TODO WIIS-9988
             $packs = $packRepository->getCurrentPackOnLocations(
                $locations,
                [
                    'isCount' => false,
                    'field' => 'pack',
                ]
            );
            $packs = Stream::from($packs)
                ->map(fn(Pack $pack) => $pack->getId())
                ->toArray();
            */
            $linked[] = [
                'type' => 'vehicle_sensor_message',
                'values' => [$vehicle->getId()],
                'entityColumn' => 'vehicle_id'
            ];

            $linked[] = [
                'type' => 'pairing_sensor_message',
                'values' => [$pairing->getId()],
                'entityColumn' => 'pairing_id'
            ];

            if (!empty($locations)) {
                $linked[] = [
                    'type' => 'emplacement_sensor_message',
                    'values' => $locations,
                    'entityColumn' => 'emplacement_id'
                ];
            }

            /*
             * TODO WIIS-9988
            if (!empty($packs)) {
                $linked[] = [
                    'type' => 'pack_sensor_message',
                    'values' => $packs,
                    'entityColumn' => 'pack_id'
                ];
            }
            */
        }

        $sensorType = $linkedDevice->getType()->getLabel();
        $sensorMessageRepository->insertRaw([
            'date' => str_replace('/', '-', $frame['timestamp']),
            'content' => $frame['value'],
            'event' => $frame['event'],
            'payload' => json_encode($frame),
            'sensor' => $linkedDevice->getId(),
            'contentType' => $sensorType === Sensor::GPS ? 4 : ($sensorType === Sensor::TEMPERATURE ? 1 : 0),
        ], $linked);
    }

    public function treatTrackLinksOnTrigger(EntityManagerInterface $entityManager,
                                             SensorWrapper          $wrapper,
                                             string                 $temperatureThresholdType): void {

        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $vehicleRepository = $entityManager->getRepository(Vehicle::class);

        $activePairing = $wrapper->getActivePairing();

        if (!$activePairing) {
            return;
        }

        $order = $activePairing->getPack()
            ?->getTransportDeliveryOrderPack()
            ?->getOrder();

        if ($order) {
            if ($temperatureThresholdType === TriggerAction::LOWER) {
                $order->setUnderThresholdExceeded(true);
            }
            else if ($temperatureThresholdType === TriggerAction::HIGHER) {
                $order->setUpperThresholdExceeded(true);
            }
        }
        else {
            $location = $activePairing->getLocation();
            if ($location) {
                $rounds = $locationRepository->findOngoingRounds($location);
            }
            else {
                $vehicle = $activePairing->getVehicle();
                $rounds = $vehicleRepository->findOngoingRounds($vehicle);
            }

            /** @var TransportRound $round */
            foreach($rounds as $round) {
                if ($temperatureThresholdType === TriggerAction::LOWER) {
                    $round->setRoundUnderThresholdExceeded(true);
                }
                else if ($temperatureThresholdType === TriggerAction::HIGHER) {
                    $round->setRoundUpperThresholdExceeded(true);
                }
            }
        }
    }

    public function validateFrame(string $profile, array $frame): bool {
        return match ($profile) {
            IOTService::INEO_SENS_ACS_TEMP_HYGRO, IOTService::INEO_SENS_ACS_HYGRO, IOTService::INEO_SENS_ACS_TEMP => str_starts_with($frame['value']['payload'], '6d'),
            IOTService::INEO_INS_EXTENDER => str_starts_with($frame['value']['payload'], '12') || str_starts_with($frame['value']['payload'], '49'),
            default => true,
        };
    }

    private function convertHexToSignedInt(string $hexStr): string {
        $dec = hexdec($hexStr);
        $isNegative = $dec & pow(16, strlen($hexStr)) / 2;
        return $isNegative
            ? $dec - pow(16, strlen($hexStr))
            : $dec;
    }
}
