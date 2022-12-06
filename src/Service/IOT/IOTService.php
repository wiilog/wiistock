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
use App\Entity\Type;
use App\Helper\FormatHelper;
use App\Repository\ArticleRepository;
use App\Repository\IOT\SensorMessageRepository;
use App\Repository\PackRepository;
use App\Repository\StatutRepository;
use App\Service\DemandeLivraisonService;
use App\Service\HttpService;
use App\Service\MailerService;
use App\Service\NotificationService;
use App\Service\UniqueNumberService;
use DateTimeZone;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class IOTService
{
    const ACS_EVENT = 'EVENT';
    const ACS_PRESENCE = 'PRESENCE';

    const INEO_SENS_ACS_TEMP = 'ineo-sens-acs';
    const INEO_SENS_ACS_BTN = 'acs-switch-bouton';
    const INEO_SENS_GPS = 'trk-tracer-gps-new';
    const SYMES_ACTION_SINGLE = 'symes-action-single';
    const SYMES_ACTION_MULTI = 'symes-action-multi';
    const KOOVEA_TAG = 'Tag température Koovea';
    const KOOVEA_HUB = 'Hub GPS Koovea';

    const PROFILE_TO_MAX_TRIGGERS = [
        self::INEO_SENS_ACS_TEMP => 1,
        self::INEO_SENS_GPS => 1,
        self::INEO_SENS_ACS_BTN => 1,
        self::SYMES_ACTION_MULTI => 4,
        self::SYMES_ACTION_SINGLE => 1,
        self::KOOVEA_TAG => 1,
        self::KOOVEA_HUB => 1,
    ];

    const PROFILE_TO_TYPE = [
        self::INEO_SENS_ACS_TEMP => Sensor::TEMPERATURE,
        self::KOOVEA_TAG => Sensor::TEMPERATURE,
        self::KOOVEA_HUB => Sensor::GPS,
        self::INEO_SENS_GPS => Sensor::GPS,
        self::INEO_SENS_ACS_BTN => Sensor::ACTION,
        self::SYMES_ACTION_MULTI => Sensor::ACTION,
        self::SYMES_ACTION_SINGLE => Sensor::ACTION,
    ];

    const PROFILE_TO_FREQUENCY = [
        self::INEO_SENS_ACS_TEMP => 'x minutes',
        self::INEO_SENS_GPS => 'x minutes',
        self::KOOVEA_TAG => 'x minutes',
        self::KOOVEA_HUB => 'x minutes',
        self::INEO_SENS_ACS_BTN => 'à l\'action',
        self::SYMES_ACTION_SINGLE => 'à l\'action',
        self::SYMES_ACTION_MULTI => 'à l\'action',
    ];

    /** @Required */
    public DemandeLivraisonService $demandeLivraisonService;

    /** @Required */
    public UniqueNumberService $uniqueNumberService;

    /** @Required */
    public AlertService $alertService;

    /** @Required */
    public NotificationService $notificationService;

    /** @Required */
    public MailerService $mailerService;

    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public HttpService $client;

    public function onMessageReceived(array $frame, EntityManagerInterface $entityManager, bool $local = false) {
        if (isset(self::PROFILE_TO_TYPE[$frame['profile']])) {
            $message = $this->parseAndCreateMessage($frame, $entityManager, $local);
            $this->linkWithSubEntities($message,
                $entityManager->getRepository(Pack::class),
                $entityManager->getRepository(Article::class),
            );
            $entityManager->flush();
            $this->treatTriggers($message, $entityManager);
            $entityManager->flush();
        }
    }

    private function treatTriggers(SensorMessage $sensorMessage, EntityManagerInterface $entityManager) {
        $sensor = $sensorMessage->getSensor();
        $wrapper = $sensor->getAvailableSensorWrapper();
        if ($wrapper) {
            foreach ($wrapper->getTriggerActions() as $triggerAction) {
                $type = FormatHelper::type($sensor->getType());
                switch ($type) {
                    case Sensor::ACTION:
                        $this->treatActionTrigger($wrapper, $triggerAction, $sensorMessage, $entityManager);
                        break;
                    case Sensor::TEMPERATURE:
                        $this->treatTemperatureTrigger($triggerAction, $sensorMessage, $entityManager, $wrapper);
                        break;
                    default:
                        break;
                }
            }
        }
        $entityManager->flush();
    }

    private function treatTemperatureTrigger(TriggerAction $triggerAction,
                                             SensorMessage $sensorMessage,
                                             EntityManagerInterface $entityManager,
                                             SensorWrapper $wrapper): void {

        $config = $triggerAction->getConfig();


        $temperatureThreshold = floatval($config['temperature']);
        $messageTemperature = floatval($sensorMessage->getContent());

        $temperatureThresholdType = $config['limit'];

        $needsTrigger = $temperatureThresholdType === TriggerAction::LOWER
            ? $temperatureThreshold >= $messageTemperature
            : $temperatureThreshold <= $messageTemperature;
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
            ->setComment(StringHelper::cleanedComment($requestTemplate->getComment()))
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
            ->setCommentaire(StringHelper::cleanedComment($requestTemplate->getComment()))
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
            ->setCommentaire(StringHelper::cleanedComment($requestTemplate->getComment()))
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

    private function parseAndCreateMessage(array $message, EntityManagerInterface $entityManager, bool $local): SensorMessage
    {
        $profileRepository = $entityManager->getRepository(SensorProfile::class);
        $deviceRepository = $entityManager->getRepository(Sensor::class);

        $profileName = $message['profile'];

        $profile = $profileRepository->findOneBy([
            'name' => $profileName,
        ]);

        if (!isset($profile)) {
            $profile = new SensorProfile();
            $profile
                ->setName($profileName)
                ->setMaxTriggers(self::PROFILE_TO_MAX_TRIGGERS[$profileName] ?? 1);
            $entityManager->persist($profile);
        }
        $entityManager->flush();
        $deviceCode = $message['device_id'];

        $device = $deviceRepository->findOneBy([
            'code' => $deviceCode,
        ]);

        if (!isset($device)) {
            $typeLabel = self::PROFILE_TO_TYPE[$profileName] ?? 'Type non détecté';
            $typeRepository = $entityManager->getRepository(Type::class);
            $type = $typeRepository->findOneBy(['label' => $typeLabel]);

            $device = new Sensor();
            $device
                ->setCode($deviceCode)
                ->setProfile($profile)
                ->setBattery(-1)
                ->setFrequency(self::PROFILE_TO_FREQUENCY[$profileName] ?? 'jamais')
                ->setType($type);
            $entityManager->persist($device);
        }

        $newBattery = $this->extractBatteryLevelFromMessage($message);
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

        $messageDate = new DateTime($message['timestamp'], $local ? null : new DateTimeZone("UTC"));
        if (!$local) {
            $messageDate->setTimezone(new DateTimeZone('Europe/Paris'));
        }

        $received = new SensorMessage();
        $received
            ->setPayload($message)
            ->setDate($messageDate)
            ->setContent($this->extractMainDataFromConfig($message))
            ->setEvent($this->extractEventTypeFromMessage($message))
            ->setLinkedSensorLastMessage($device)
            ->setSensor($device);

        $entityManager->persist($received);
        return $received;
    }

    public function linkWithSubEntities(SensorMessage $sensorMessage, PackRepository $packRepository, ArticleRepository $articleRepository) {
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
                'field' => 'colis',
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

    public function extractMainDataFromConfig(array $config) {
        switch ($config['profile']) {
            case IOTService::KOOVEA_TAG:
            case IOTService::KOOVEA_HUB:
                return $config['value'];
            case IOTService::INEO_SENS_ACS_BTN:
                return $this->extractEventTypeFromMessage($config);
            case IOTService::SYMES_ACTION_MULTI:
            case IOTService::SYMES_ACTION_SINGLE:
                if (isset($config['payload_cleartext'])) {
                    $value = hexdec(substr($config['payload_cleartext'], 0, 2));
                    $event =  $value & ~($value >> 3 << 3);
                    return $event === 0 ? self::ACS_PRESENCE : (self::ACS_EVENT . " (" . $event . ")");
                }
                break;
            case IOTService::INEO_SENS_ACS_TEMP:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_temperature'];
                }
                break;
            case IOTService::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    if (isset($frame['LATITUDE']) && isset($frame['LONGITUDE'])) {
                        return $frame['LATITUDE'] . ',' . $frame['LONGITUDE'];
                    } else {
                        return '-1,-1';
                    }
                }
                break;
        }
        return 'Donnée principale non trouvée';
    }

    public function extractEventTypeFromMessage(array $config) {
        switch ($config['profile']) {
            case IOTService::KOOVEA_TAG:
            case IOTService::KOOVEA_HUB:
                return $config['event'];
            case IOTService::INEO_SENS_ACS_BTN:
            case IOTService::INEO_SENS_ACS_TEMP:
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
                if (isset($config['payload_cleartext'])) {
                    $value = hexdec(substr($config['payload_cleartext'], 0, 2));
                    $event =  $value & ~($value >> 3 << 3);
                    return $event === 0 ? self::ACS_PRESENCE : self::ACS_EVENT;
                }
                break;
        }
        return 'Évenement non trouvé';
    }

    public function extractBatteryLevelFromMessage(array $config) {
        switch ($config['profile']) {
            case IOTService::KOOVEA_TAG:
            case IOTService::KOOVEA_HUB:
                return -1;
            case IOTService::INEO_SENS_ACS_BTN:
            case IOTService::INEO_SENS_ACS_TEMP:
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
                $tensionBites = substr($config['payload_cleartext'], 20, 2);
                $level = hexdec($tensionBites) >> 1;
                $minVoltage = 2400;
                $maxVoltage = 3700;
                $incertitudeLevel = 10;
                $currentVoltage = $level * $incertitudeLevel + $minVoltage;
                return (($currentVoltage - $minVoltage) / ($maxVoltage - $minVoltage)) * 100;
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

            $packs = $packRepository->getCurrentPackOnLocations(
                $locations,
                [
                    'isCount' => false,
                    'field' => 'colis',
                ]
            );
            $packs = Stream::from($packs)
                ->map(fn(Pack $pack) => $pack->getId())
                ->toArray();

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
            if (!empty($packs)) {
                $linked[] = [
                    'type' => 'pack_sensor_message',
                    'values' => $packs,
                    'entityColumn' => 'pack_id'
                ];
            }
        }

        $sensorMessageRepository->insertRaw([
            'date' => str_replace('/', '-', $frame['timestamp']),
            'content' => $frame['value'],
            'event' => $frame['event'],
            'payload' => json_encode($frame),
            'sensor' => $linkedDevice->getId(),
        ], $linked);
    }

    public function treatTrackLinksOnTrigger(EntityManagerInterface $entityManager,
                                             SensorWrapper          $wrapper,
                                             string                 $temperatureThresholdType): void {

        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $vehicleRepository = $entityManager->getRepository(Vehicle::class);

        $activePairing = $wrapper->getActivePairing();
        $order = $activePairing->getPack()
            ->getTransportDeliveryOrderPack()
            ->getOrder();

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
}
