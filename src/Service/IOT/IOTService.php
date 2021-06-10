<?php


namespace App\Service\IOT;


use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\Handling;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorProfile;
use App\Entity\IOT\SensorWrapper;
use App\Entity\IOT\TriggerAction;
use App\Entity\LigneArticle;
use App\Entity\LocationGroup;
use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;
use App\Entity\Pack;
use App\Entity\Preparation;
use App\Entity\Statut;
use App\Repository\PackRepository;
use App\Repository\StatutRepository;
use App\Service\DemandeLivraisonService;
use App\Service\UniqueNumberService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;

class IOTService
{
    const ACS_EVENT = 'EVENT';
    const ACS_PRESENCE = 'PRESENCE';

    const INEO_SENS_ACS_TEMP = 'ineo-sens-acs';
    const INEO_SENS_ACS_BTN = 'acs-switch-bouton';
    const INEO_SENS_GPS = 'trk-tracer-gps-new';

    const PROFILE_TO_MAX_TRIGGERS = [
        self::INEO_SENS_ACS_TEMP => 1,
        self::INEO_SENS_GPS => 1,
        self::INEO_SENS_ACS_BTN => 1,
    ];

    const PROFILE_TO_TYPE = [
        self::INEO_SENS_ACS_TEMP => Sensor::TEMPERATURE,
        self::INEO_SENS_GPS => Sensor::GPS,
        self::INEO_SENS_ACS_BTN => Sensor::ACTION,
    ];

    const PROFILE_TO_FREQUENCY = [
        self::INEO_SENS_ACS_TEMP => 'x minutes',
        self::INEO_SENS_GPS => 'x minutes',
        self::INEO_SENS_ACS_BTN => 'à l\'action',
    ];

    /** @Required */
    public DemandeLivraisonService $demandeLivraisonService;

    /** @Required */
    public UniqueNumberService $uniqueNumberService;

    /** @Required */
    public AlertService $alertService;

    public function onMessageReceived(array $frame, EntityManagerInterface $entityManager) {
        if (isset(self::PROFILE_TO_TYPE[$frame['profile']])) {
            $message = $this->parseAndCreateMessage($frame, $entityManager);
            $this->linkWithSubEntities($message, $entityManager->getRepository(Pack::class));
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
                $type = $sensor->getType();
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

    private function treatTemperatureTrigger(TriggerAction $triggerAction, SensorMessage $sensorMessage, EntityManagerInterface $entityManager, SensorWrapper $wrapper) {
        $config = $triggerAction->getConfig();


        $temperatureTreshold = floatval($config['temperature']);
        $messageTemperature = floatval($sensorMessage->getContent());

        $temperatureTresholdType = $config['limit'];

        $needsTrigger = $temperatureTresholdType === 'lower' ?
            $temperatureTreshold >= $messageTemperature
            : $temperatureTreshold <= $messageTemperature;
        $triggerAction->setLastTrigger(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
        if ($needsTrigger) {
            if ($triggerAction->getRequestTemplate()) {
                $this->treatRequestTemplateTriggerType($triggerAction->getRequestTemplate(), $entityManager, $wrapper);
            } else if ($triggerAction->getAlertTemplate()) {
                $this->treatAlertTemplateTriggerType($triggerAction->getAlertTemplate(), $sensorMessage);
            }
        }
    }

    private function treatActionTrigger(SensorWrapper $wrapper, TriggerAction $triggerAction, SensorMessage $sensorMessage, EntityManagerInterface $entityManager) {
        $needsTrigger = $sensorMessage->getEvent() === self::ACS_EVENT;
        if ($needsTrigger) {
            if ($triggerAction->getRequestTemplate()) {
                $this->treatRequestTemplateTriggerType($triggerAction->getRequestTemplate(), $entityManager, $wrapper);
            } else if ($triggerAction->getAlertTemplate()) {
                $this->treatAlertTemplateTriggerType($triggerAction->getAlertTemplate(), $sensorMessage);
            }
        }
    }

    private function treatRequestTemplateTriggerType(RequestTemplate $requestTemplate, EntityManagerInterface $entityManager, SensorWrapper $wrapper) {
        $statutRepository = $entityManager->getRepository(Statut::class);

        if ($requestTemplate instanceof DeliveryRequestTemplate) {
            $request = $this->cleanCreateDeliveryRequest($statutRepository, $entityManager, $wrapper, $requestTemplate);
            $entityManager->persist($request);
            $valid = true;
            $entityManager->flush();
            foreach ($request->getLigneArticle() as $ligneArticle) {
                $reference = $ligneArticle->getReference();
                if ($reference->getQuantiteDisponible() < $ligneArticle->getQuantite()) {
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
            $request = $this->cleanCreateHandlingRequest($entityManager, $wrapper, $requestTemplate);
            $entityManager->persist($request);
            $entityManager->flush();
        }
    }

    private function cleanCreateHandlingRequest(EntityManagerInterface $entityManager,
                                                SensorWrapper $sensorWrapper,
                                                HandlingRequestTemplate $requestTemplate): Handling {
        $handling = new Handling();
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $handlingNumber = $this->uniqueNumberService->createUniqueNumber($entityManager, Handling::PREFIX_NUMBER, Handling::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

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
            ->setFromSensor($sensorWrapper)
            ->setNumber($handlingNumber)
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
        $numero = $this->demandeLivraisonService->generateNumeroForNewDL($entityManager);
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

        $request = new Demande();
        $request
            ->setStatut($statut)
            ->setDate($date)
            ->setCommentaire($requestTemplate->getComment())
            ->setFromSensor($wrapper)
            ->setType($requestTemplate->getRequestType())
            ->setDestination($requestTemplate->getDestination())
            ->setNumero($numero)
            ->setFreeFields($requestTemplate->getFreeFields());

        foreach ($requestTemplate->getLines() as $requestTemplateLine) {
            $ligneArticle = new LigneArticle();
            $ligneArticle
                ->setReference($requestTemplateLine->getReference())
                ->setDemande($request)
                ->setQuantite($requestTemplateLine->getQuantityToTake()); // protection contre quantités négatives
            $entityManager->persist($ligneArticle);
            $request->addLigneArticle($ligneArticle);
        }
        return $request;
    }

    private function cleanCreateCollectRequest(StatutRepository $statutRepository,
                                                EntityManagerInterface $entityManager,
                                                SensorWrapper $wrapper,
                                                CollectRequestTemplate $requestTemplate): Collecte {
        $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $numero = 'C-' . $date->format('YmdHis');
        $status = $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_BROUILLON);

        $request = new Collecte();
        $request
            ->setFromSensor($wrapper)
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
        $this->cleanCreateCollectOrder($statutRepository, $request, $entityManager);
        $entityManager->flush();
        return $request;
    }

    private function cleanCreateCollectOrder(StatutRepository $statutRepository, Collecte $demandeCollecte, EntityManagerInterface $entityManager) {

        $statut = $statutRepository
            ->findOneByCategorieNameAndStatutCode(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_A_TRAITER);
        $ordreCollecte = new OrdreCollecte();
        $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
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
    }

    private function treatAlertTemplateTriggerType(AlertTemplate $template, SensorMessage $message) {
        $this->alertService->trigger($template, $message);
    }

    private function parseAndCreateMessage(array $message, EntityManagerInterface $entityManager): SensorMessage
    {
        $profileRepository = $entityManager->getRepository(SensorProfile::class);
        $deviceRepository = $entityManager->getRepository(Sensor::class);

        $profileName = $message['profile'];

        $profile = $profileRepository->findOneBy([
            'name' => $profileName
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
            'code' => $deviceCode
        ]);

        if (!isset($device)) {
            $device = new Sensor();
            $device
                ->setCode($deviceCode)
                ->setProfile($profile)
                ->setBattery(-1)
                ->setFrequency(self::PROFILE_TO_FREQUENCY[$profileName] ?? 'jamais')
                ->setType(self::PROFILE_TO_TYPE[$profileName] ?? 'Type non détecté');
            $entityManager->persist($device);
        }

        $newBattery = $this->extractBatteryLevelFromMessage($message);
        if ($newBattery > -1) {
            $device->setBattery($newBattery);
        }
        $entityManager->flush();

        $messageDate = new \DateTime($message['timestamp'], new \DateTimeZone("UTC"));
        $messageDate->setTimezone(new \DateTimeZone('Europe/Paris'));
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

    public function linkWithSubEntities(SensorMessage $sensorMessage, PackRepository $packRepository) {
        $sensor = $sensorMessage->getSensor();
        $wrapper = $sensor->getAvailableSensorWrapper();

        if ($wrapper) {
            foreach ($wrapper->getPairings() as $pairing) {
                if ($pairing->getActive()) {
                    $pairing->addSensorMessage($sensorMessage);
                    $entity = $pairing->getEntity();
                    if ($entity instanceof LocationGroup) {
                        $this->treatAddMessageLocationGroup($entity, $sensorMessage, $packRepository);
                    } else if ($entity instanceof Emplacement) {
                        $this->treatAddMessageLocation($entity, $sensorMessage, $packRepository);
                    } else if ($entity instanceof Pack) {
                        $this->treatAddMessagePack($entity, $sensorMessage);
                    } else if ($entity instanceof Article) {
                        $this->treatAddMessageArticle($entity, $sensorMessage);
                    } else if ($entity instanceof Preparation) {
                        $this->treatAddMessageOrdrePrepa($entity, $sensorMessage);
                    } else if ($entity instanceof OrdreCollecte) {
                        $this->treatAddMessageOrdreCollecte($entity, $sensorMessage);
                    }
                }
            }
        }
    }

    private function treatAddMessageLocationGroup(LocationGroup $locationGroup, SensorMessage $sensorMessage, PackRepository $packRepository) {
        $locationGroup->addSensorMessage($sensorMessage);
        foreach ($locationGroup->getLocations() as $location) {
            $this->treatAddMessageLocation($location, $sensorMessage, $packRepository);
        }
    }

    private function treatAddMessageLocation(Emplacement $location, SensorMessage $sensorMessage, PackRepository $packRepository) {
        $location->addSensorMessage($sensorMessage);
        $packs = $packRepository->getCurrentPackOnLocations(
            [$location->getId()],
            [
                'isCount' => false,
                'field' => 'colis'
            ]
        );
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

    private function treatAddMessageOrdrePrepa(Preparation $preparation, SensorMessage $sensorMessage) {
        $preparation->addSensorMessage($sensorMessage);
        foreach ($preparation->getArticles() as $article) {
            $this->treatAddMessageArticle($article, $sensorMessage);
        }
    }

    private function treatAddMessageOrdreCollecte(OrdreCollecte $ordreCollecte, SensorMessage $sensorMessage) {
        $ordreCollecte->addSensorMessage($sensorMessage);
        foreach ($ordreCollecte->getArticles() as $article) {
            $this->treatAddMessageArticle($article, $sensorMessage);
        }
    }

    public function extractMainDataFromConfig(array $config) {
        switch ($config['profile']) {
            case IOTService::INEO_SENS_ACS_BTN:
                return $this->extractEventTypeFromMessage($config);
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
        }
        return 'Évenement non trouvé';
    }

    public function extractBatteryLevelFromMessage(array $config) {
        switch ($config['profile']) {
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
        }
        return 'Évenement non trouvé';
    }
}
