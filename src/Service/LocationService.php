<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\FormException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class LocationService {

    const PAGE_EMPLACEMENT = 'emplacement';
    private array $labeledCacheLocations = [];

    public function __construct(
        private FieldModesService      $fieldModesService,
        private UserService            $userService,
        private FormatService          $formatService,
        private EntityManagerInterface $entityManager,
        private Security               $security,
        private RouterInterface        $router,
        private Twig_Environment       $templating,
        private CSVExportService       $csvExportService,
    ){}

    public function persistLocation(EntityManagerInterface $entityManager,
                                    ParameterBag|array     $data,
                                    bool                   $viaUserForm = false): Emplacement {
        $location = new Emplacement();
        $data = !($data instanceof ParameterBag) ? new ParameterBag($data) : $data;

        $this->updateLocation($entityManager, $location, $data, $viaUserForm);

        $entityManager->persist($location);

        return $location;
    }

    public function updateLocation(EntityManagerInterface $entityManager,
                                   Emplacement            $location,
                                   ParameterBag           $data,
                                   bool                   $viaUserForm): void {

        $typeRepository = $entityManager->getRepository(Type::class);
        $zoneRepository = $entityManager->getRepository(Zone::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $naturesRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        if ($viaUserForm) {
            $this->checkValidity($entityManager, $location, $data);
        }

        if(!empty($data->get(FixedFieldEnum::zone->name))) {
            $zone = $zoneRepository->find($data->get(FixedFieldEnum::zone->name))
                ?? $zoneRepository->findOneBy(['name' => Zone::ACTIVITY_STANDARD_ZONE_NAME]);
        } else {
            $zone = $zoneRepository->findOneBy(['name' => Zone::ACTIVITY_STANDARD_ZONE_NAME]);
        }

        if(!empty($data->get(FixedFieldEnum::signatories->name))) {
            $signatoryIds = is_array($data->get(FixedFieldEnum::signatories->name))
                ? $data->get(FixedFieldEnum::signatories->name)
                : Stream::explode(',', $data->get(FixedFieldEnum::signatories->name, ''))
                    ->filterMap(fn(string $id) => trim($id) ?: null)
                    ->toArray();

            $signatories = !empty($signatoryIds)
                ? $userRepository->findBy(['id' => $signatoryIds])
                : [];
        }

        $managerIds = Stream::explode(",", $data->get(FixedFieldEnum::managers->name, ''))
            ->filterMap(fn(string $id) => trim($id) ?: null)
            ->toArray();
        if (!empty($managerIds)) {
            $managers = $userRepository->findBy(["id" => $managerIds]);
        }

        $allowedNatureIds = Stream::explode(",", $data->get(FixedFieldEnum::allowedNatures->name, ""))
            ->filterMap(static fn(string $id) => trim($id) ?: null)
            ->toArray();

        if (!empty($allowedNatureIds)) {
            $allowedNatures = $naturesRepository->findBy(["id" => $allowedNatureIds]);
        }

        $allowedCollectTypeIds = Stream::explode(",", $data->get(FixedFieldEnum::allowedCollectTypes->name, ""))
            ->filterMap(fn(string $id) => trim($id) ?: null)
            ->toArray();
        if (!empty($allowedCollectTypeIds)) {
            $allowedCollectTypes = $typeRepository->findBy(["id" => $allowedCollectTypeIds]);
        }

        $allowedDeliveryTypeIds = Stream::explode(",", $data->get(FixedFieldEnum::allowedDeliveryTypes->name, ""))
            ->filterMap(fn(string $id) => trim($id) ?: null)
            ->toArray();
        if (!empty($allowedDeliveryTypeIds)) {
            $allowedDeliveryTypes = $typeRepository->findBy(["id" => $allowedDeliveryTypeIds]);
        }

        $allowedTemperatureIds = Stream::explode(",", $data->get(FixedFieldEnum::allowedTemperatures->name, ""))
            ->filterMap(fn(string $id) => trim($id) ?: null)
            ->toArray();
        if (!empty($allowedTemperatureIds)) {
            $allowedTemperatures = $temperatureRangeRepository->findBy(["id" => $allowedTemperatureIds]);
        }

        $location
            ->setLabel($data->get(FixedFieldEnum::name->name))
            ->setDescription($data->get(FixedFieldEnum::description->name))
            ->setIsActive($data->getBoolean(FixedFieldEnum::status->name))
            ->setSendEmailToManagers($data->getBoolean(FixedFieldEnum::sendEmailToManagers->name))
            ->setDateMaxTime($data->get(FixedFieldEnum::maximumTrackingDelay->name))
            ->setIsDeliveryPoint($data->getBoolean(FixedFieldEnum::isDeliveryPoint->name))
            ->setIsOngoingVisibleOnMobile($data->getBoolean(FixedFieldEnum::isOngoingVisibleOnMobile->name))
            ->setAllowedDeliveryTypes($allowedDeliveryTypes ?? [])
            ->setAllowedCollectTypes($allowedCollectTypes ?? [])
            ->setAllowedNatures($allowedNatures ?? [])
            ->setManagers($managers ?? [])
            ->setTemperatureRanges($allowedTemperatures ?? [])
            ->setSignatories($signatories ?? [])
            ->setEmail($data->get(FixedFieldEnum::email->name))
            ->setStartTrackingTimerOnPicking($data->getBoolean(FixedFieldEnum::startTrackingTimerOnPicking->name))
            ->setPauseTrackingTimerOnDrop($data->getBoolean(FixedFieldEnum::pauseTrackingTimerOnDrop->name))
            ->setStopTrackingTimerOnDrop($data->getBoolean(FixedFieldEnum::stopTrackingTimerOnDrop->name))
            ->setNewNatureOnPick(
                !empty($data->get(FixedFieldEnum::newNatureOnPick->name))
                    ? $naturesRepository->findOneBy(["id" => $data->get(FixedFieldEnum::newNatureOnPick->name)])
                    : null
            )
            ->setNewNatureOnDrop(
                !empty($data->get(FixedFieldEnum::newNatureOnDrop->name))
                    ? $naturesRepository->findOneBy(["id" => $data->get(FixedFieldEnum::newNatureOnDrop->name)])
                    : null
            )
            ->setProperty(FixedFieldEnum::zone->name, $zone)
            ->setNewNatureOnPickEnabled($data->getBoolean("enableNewNatureOnPick"))
            ->setNewNatureOnDropEnabled($data->getBoolean("enableNewNatureOnDrop"));
    }

    public function getEmplacementDataByParams($params = null): array {
        $user = $this->security->getUser();

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);

        $filterStatus = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, self::PAGE_EMPLACEMENT, $user);
        $active = $filterStatus ? $filterStatus->getValue() : false;

        $queryResult = $emplacementRepository->findByParamsAndExcludeInactive($params, $active);

        $emplacements = $queryResult['data'];
        $listId = $queryResult['allEmplacementDataTable'];

        $emplacementsString = [];
        foreach ($listId as $id) {
            $emplacementsString[] = $id->getId();
        }

        $rows = [];
        foreach ($emplacements as $emplacement) {
            $rows[] = $this->dataRowEmplacement($this->entityManager, $emplacement);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
            'listId' => $emplacementsString,
        ];
    }

    public function dataRowEmplacement(EntityManagerInterface $manager, Emplacement $location): array {
        $url['edit'] = $this->router->generate('emplacement_edit', ['id' => $location->getId()]);

        $sensorMessageRepository = $manager->getRepository(SensorMessage::class);

        $allowedNatures = Stream::from($location->getAllowedNatures())
            ->map(fn(Nature $nature) => $this->formatService->nature($nature))
            ->join(", ");

        $allowedTemperatures = Stream::from($location->getTemperatureRanges())
            ->map(fn(TemperatureRange $temperature) => $temperature->getValue())
            ->join(", ");

        $linkedGroup = $location->getLocationGroup();
        $groupLastMessage = $linkedGroup ?  $sensorMessageRepository->getLastSensorMessage($linkedGroup) : null;
        $locationLastMessage = $sensorMessageRepository->getLastSensorMessage($location);

        $sensorCode = $groupLastMessage && $groupLastMessage->getSensor()->getAvailableSensorWrapper()
            ? $groupLastMessage->getSensor()->getAvailableSensorWrapper()->getName()
            : ($locationLastMessage && $locationLastMessage->getSensor()->getAvailableSensorWrapper()
                ? $locationLastMessage->getSensor()->getAvailableSensorWrapper()->getName()
                : null);

        $hasPairing = !$location->getPairings()->isEmpty() || !$location->getSensorMessages()->isEmpty();

        return [
            'id' => $location->getId(),
            FixedFieldEnum::name->name => $location->getLabel() ?: 'Non défini',
            FixedFieldEnum::description->name => $location->getDescription() ?: 'Non défini',
            FixedFieldEnum::isDeliveryPoint->name => $this->formatService->bool($location->getIsDeliveryPoint()),
            FixedFieldEnum::isOngoingVisibleOnMobile->name => $this->formatService->bool($location->isOngoingVisibleOnMobile()),
            FixedFieldEnum::maximumTrackingDelay->name => $location->getDateMaxTime() ?? '',
            FixedFieldEnum::status->name => $location->getIsActive() ? 'actif' : 'inactif',
            FixedFieldEnum::allowedNatures->name => $allowedNatures,
            FixedFieldEnum::allowedTemperatures->name => $allowedTemperatures,
            FixedFieldEnum::signatories->name => $this->formatService->users($location->getSignatories()),
            FixedFieldEnum::email->name => $location->getEmail(),
            FixedFieldEnum::zone->name => $location->getZone() ? $location->getZone()->getName() : "",
            FixedFieldEnum::managers->name => $this->formatService->users($location->getManagers()),
            FixedFieldEnum::sendEmailToManagers->name => $this->formatService->bool($location->isSendEmailToManagers()),
            "actions" => $this->templating->render("utils/action-buttons/dropdown.html.twig", [
                "actions" => [
                    [
                        "title" => "Modifier",
                        "hasRight" => $this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT),
                        "actionOnClick" => true,
                        "class" => "edit-location",
                        "attributes" => [
                            "data-id" => $location->getId(),
                        ],
                    ],
                    [
                        "title" => "Imprimer",
                        "icon" => "wii-icon wii-icon-printer-black",
                        "href" => $this->router->generate('print_single_location_bar_code', ['location' => $location->getId()]),
                    ],
                    [
                        "title" => "Historique des données",
                        "icon" => "wii-icon wii-icon-pairing",
                        "hasRight" => (
                            $hasPairing
                            && $this->userService->hasRightFunction(Menu::IOT, Action::DISPLAY_SENSOR)
                        ),
                        "href" => $this->router->generate('show_data_history', ['id' => $location->getId(), "type" => Sensor::LOCATION]),
                    ],
                    [
                        "title" => "Supprimer",
                        "icon" => "wii-icon wii-icon-trash-black",
                        "hasRight" => $this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE),
                        "class" => "delete-location",
                        "attributes" => [
                            "data-id" => $location->getId(),
                        ],
                    ],
                ],
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
            'startTrackingTimerOnPicking' => $this->formatService->bool($location->isStartTrackingTimerOnPicking()),
            'stopTrackingTimerOnDrop' => $this->formatService->bool($location->isStopTrackingTimerOnDrop()),
            'pauseTrackingTimerOnDrop' => $this->formatService->bool($location->isPauseTrackingTimerOnDrop()),
            'enableNewNatureOnPick' => $this->formatService->bool($location->isNewNatureOnPickEnabled()),
            'enableNewNatureOnDrop' => $this->formatService->bool($location->isNewNatureOnDropEnabled()),
            'allowedDeliveryTypes' => Stream::from($location->getAllowedDeliveryTypes())
                ->map(fn(Type $allowedDeliveryTypes): string => $this->formatService->type($allowedDeliveryTypes))
                ->join(", "),
            'newNatureOnPick' => $this->formatService->nature($location->getNewNatureOnPick()),
            'newNatureOnDrop' => $this->formatService->nature($location->getNewNatureOnDrop()),
        ];
    }

    public function findOrPersistWithCache(EntityManagerInterface $entityManager,
                                           string $label,
                                           bool &$mustReload): Emplacement {

        if ($mustReload) {
            $mustReload = false;
            $this->labeledCacheLocations = [];
        }

        if (isset($this->labeledCacheLocations[$label])) {
            $location = $this->labeledCacheLocations[$label];
        }
        else {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $location = $emplacementRepository->findOneBy(['label' => $label]);
        }

        if (!$location) {
            $location = $this->persistLocation($entityManager, [
                FixedFieldEnum::name->name => $label,
            ]);
        }

        return $location;
    }

    public function checkValidity(EntityManagerInterface $entityManager,
                                  Emplacement            $location,
                                  ParameterBag           $data): void {
        $email = $data->get(FixedFieldEnum::email->name);;
        if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new FormException("L'adresse email renseignée est invalide.");
        }

        $allowedNatures = Stream::explode(',', $data->get(FixedFieldEnum::allowedNatures->name, ''))
            ->filter()
            ->values();
        $newNatureOnDrop = $data->get(FixedFieldEnum::newNatureOnDrop->name);;
        if (!empty($allowedNatures) && $newNatureOnDrop && !in_array($newNatureOnDrop, $allowedNatures)) {
            throw new FormException("La nouvelle nature à la dépose sur emplacement doit être présente dans les natures autorisées.");
        }

        $dateMaxTime = $data->get(FixedFieldEnum::maximumTrackingDelay->name);
        if (!empty($dateMaxTime)) {
            $matchHours = '\d+';
            $matchMinutes = '([0-5][0-9])';
            $matchHoursMinutes = "$matchHours:$matchMinutes";
            $resultFormat = preg_match(
                "/^$matchHoursMinutes$/",
                $dateMaxTime
            );
            if (empty($resultFormat)) {
                throw new FormException("Le délai saisi est invalide.");
            }
        }

        $locationLabel = $data->get(FixedFieldEnum::name->name);

        // if $label matches the regex, it's valid
        if (!preg_match("/" . SettingsService::CHARACTER_VALID_REGEX . "/", $locationLabel)) {
            throw new FormException("Le nom de l'emplacement doit contenir au maximum 24 caractères, lettres ou chiffres uniquement, pas d’accent");
        }

        $labelTrimmed = $locationLabel ? trim($locationLabel) : null;
        if (!empty($labelTrimmed)) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $emplacementAlreadyExist = $emplacementRepository->countByLabel($locationLabel, $location->getId());
            if ($emplacementAlreadyExist) {
                throw new FormException("Ce nom d'emplacement existe déjà. Veuillez en choisir un autre.");
            }
        } else {
            throw new FormException("Vous devez donner un nom valide.");
        }
    }

    public function getColumnVisibleConfig(?Utilisateur $currentUser,
                                           string $page): array {

        $fieldsModes = $currentUser ? $currentUser->getFieldModes($page) ?? Utilisateur::DEFAULT_FIELDS_MODES[$page] : [];

        $columns = [
            ['name' => 'actions','class' => 'noVis', 'orderable' => false, 'alwaysVisible' => true],
            ['name' => 'pairing', 'class' => 'pairing-now', 'orderable' => false, 'alwaysVisible' => true],
            ['name' => 'name', 'title' => 'Nom', 'orderable' => true, 'alwaysVisible' => true],
            ['name' => 'description', 'title' => 'Description', 'orderable' => true],
            ['name' => 'isDeliveryPoint', 'title' => 'Point de livraison', 'orderable' => true],
            ['name' => 'isOngoingVisibleOnMobile', 'title' => 'Encours visible sur nomade', 'orderable' => true],
            ['name' => 'maximumTrackingDelay', 'title' => 'Délai maximum de traçabilité', 'orderable' => true],
            ['name' => 'status', 'title' => 'Statut', 'orderable' => true],
            ['name' => 'allowedNatures', 'title' => 'Natures autorisées', 'orderable' => false],
            ['name' => 'allowedTemperatures', 'title' => 'Températures autorisées', 'orderable' => false],
            ['name' => 'signatories', 'title' => 'Signataires', 'orderable' => false],
            ['name' => 'email', 'title' => 'Email'],
            ['name' => 'zone', 'title' => 'Zone'],
            ['name' => 'sendEmailToManagers', 'title' => "Envoi d'email à chaque dépose aux responsables de l'emplacement", 'orderable' => false],
            ['name' => 'managers', 'title' => 'Responsables', 'orderable' => false],
            ['name' => 'allowedDeliveryTypes', 'title' => 'Types de livraison autorisés', 'orderable' => false],
            ['name' => 'startTrackingTimerOnPicking', 'title' => 'Emplacement de prise initiale'],
            ['name' => 'stopTrackingTimerOnDrop', 'title' => 'Emplacement de dépose finale'],
            ['name' => 'pauseTrackingTimerOnDrop', 'title' => 'Emplacement de pause'],
            ['name' => 'enableNewNatureOnPick', 'title' => 'Activer le changement de nature à la prise'],
            ['name' => 'enableNewNatureOnDrop', 'title' => 'Activer le changement de nature à la dépose'],
            ['name' => 'newNatureOnPick', 'title' => 'Nouvelle nature à la prise sur emplacement'],
            ['name' => 'newNatureOnDrop', 'title' => 'Nouvelle nature à la dépose sur emplacement'],
        ];

        return $this->fieldModesService->getArrayConfig($columns, [], $fieldsModes);
    }

    public function getExportFunction(EntityManagerInterface $entityManager): callable {
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $locations = $locationRepository->findAll();

        return function ($handle) use ($locations) {
            foreach ($locations as $location) {
                $allowedNatures = Stream::from($location->getAllowedNatures())
                    ->map(fn(Nature $nature) => $this->formatService->nature($nature))
                    ->join(", ");

                $allowedTemperatures = Stream::from($location->getTemperatureRanges())
                    ->map(fn(TemperatureRange $temperature) => $temperature->getValue())
                    ->join(", ");

                $allowedDeliveryTypes = Stream::from($location->getAllowedDeliveryTypes())
                    ->map(fn(Type $type) => $this->formatService->type($type))
                    ->join(", ");

                $allowedCollectTypes = Stream::from($location->getAllowedCollectTypes())
                    ->map(fn(Type $type) => $this->formatService->type($type))
                    ->join(", ");


                $this->csvExportService->putLine($handle, [
                    $this->formatService->location($location->getLabel(), 'Non défini'),
                    $location->getDescription() ?: 'Non défini',
                    $this->formatService->bool($location->getIsDeliveryPoint()),
                    $this->formatService->bool($location->isOngoingVisibleOnMobile()),
                    $location->getDateMaxTime() ?? '',
                    $location->getIsActive() ? 'actif' : 'inactif',
                    $allowedNatures,
                    $allowedTemperatures,
                    $this->formatService->users($location->getSignatories()),
                    $location->getEmail(),
                    $this->formatService->zone($location->getZone()),
                    $this->formatService->users($location->getManagers()),
                    $this->formatService->bool($location->isSendEmailToManagers()),
                    $allowedDeliveryTypes,
                    $allowedCollectTypes,
                    $this->formatService->bool($location->isStartTrackingTimerOnPicking()),
                    $this->formatService->bool($location->isStopTrackingTimerOnDrop()),
                    $this->formatService->bool($location->isPauseTrackingTimerOnDrop()),
                    $this->formatService->nature($location->getNewNatureOnPick()),
                    $this->formatService->nature($location->getNewNatureOnDrop()),
                    $this->formatService->bool($location->isNewNatureOnPickEnabled()),
                    $this->formatService->bool($location->isNewNatureOnDropEnabled()),
                ]);
            }
        };
    }

    public function getCsvHeader (): array {
        return [
            FixedFieldEnum::name->value,
            FixedFieldEnum::description->value,
            FixedFieldEnum::isDeliveryPoint->value,
            FixedFieldEnum::isOngoingVisibleOnMobile->value,
            FixedFieldEnum::maximumTrackingDelay->value,
            FixedFieldEnum::status->value,
            FixedFieldEnum::allowedNatures->value,
            FixedFieldEnum::allowedTemperatures->value,
            FixedFieldEnum::signatories->value,
            FixedFieldEnum::email->value,
            FixedFieldEnum::zone->value,
            FixedFieldEnum::managers->value,
            FixedFieldEnum::sendEmailToManagers->value,
            FixedFieldEnum::allowedDeliveryTypes->value,
            FixedFieldEnum::allowedCollectTypes->value,
            FixedFieldEnum::startTrackingTimerOnPicking->value,
            FixedFieldEnum::stopTrackingTimerOnDrop->value,
            FIxedFieldEnum::pauseTrackingTimerOnDrop->value,
            FixedFieldEnum::newNatureOnPick->value,
            FixedFieldEnum::newNatureOnDrop->value,
            'Activer le changement de nature à la prise',
            'Activer le changement de nature à la dépose',
        ];
    }
}
