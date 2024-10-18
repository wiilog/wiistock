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
use App\Entity\Type;
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

class EmplacementDataService {

    const PAGE_EMPLACEMENT = 'emplacement';

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public Security $security;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UserService $userService;

    private array $labeledCacheLocations = [];

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
            ->setEnableNewNatureOnPick($data->getBoolean("EnableNewNatureOnPick"))
            ->setEnableNewNatureOnDrop($data->getBoolean("EnableNewNatureOnDrop"));
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
            ->toArray();
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
}
