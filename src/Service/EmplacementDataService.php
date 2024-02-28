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
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\SecurityBundle\Security;
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

    public function persistLocation(array $data, EntityManagerInterface $entityManager): Emplacement
    {
        $typeRepository = $entityManager->getRepository(Type::class);
        $zoneRepository = $entityManager->getRepository(Zone::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $naturesRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        if(!empty($data[FixedFieldEnum::zone->name])) {
            $zone = $zoneRepository->find($data[FixedFieldEnum::zone->name]) ?? $zoneRepository->findOneBy(['name' => Zone::ACTIVITY_STANDARD_ZONE_NAME]);
        } else {
            $zone = $zoneRepository->findOneBy(['name' => Zone::ACTIVITY_STANDARD_ZONE_NAME]);
        }

        if(!empty($data[FixedFieldEnum::signatories->name])) {
            $signatoryIds = is_array($data[FixedFieldEnum::signatories->name])
                ? $data[FixedFieldEnum::signatories->name]
                : Stream::explode(',', $data[FixedFieldEnum::signatories->name])
                    ->filter()
                    ->map(fn(string $id) => trim($id))
                    ->toArray();

            $signatories = !empty($signatoryIds)
                ? $userRepository->findBy(['id' => $signatoryIds])
                : [];
        }

        if (!empty($data[FixedFieldEnum::managers->name])) {
            $managerIds = Stream::explode(",", $data[FixedFieldEnum::managers->name])
                ->filter()
                ->toArray();
            if (!empty($managerIds)) {
                $managers = $userRepository->findBy(["id" => $managerIds]);
            }
        }

        $location = (new Emplacement())
            ->setLabel($data[FixedFieldEnum::name->name])
            ->setDescription($data[FixedFieldEnum::description->name] ?? null)
            ->setIsActive($data[FixedFieldEnum::status->name] ?? true)
            ->setSendEmailToManagers($data[FixedFieldEnum::sendEmailToManagers->name] ?? false)
            ->setDateMaxTime($data[FixedFieldEnum::maximumTrackingDelay->name] ?? null)
            ->setIsDeliveryPoint($data[FixedFieldEnum::isDeliveryPoint->name] ?? null)
            ->setIsOngoingVisibleOnMobile($data[FixedFieldEnum::isOngoingVisibleOnMobile->name] ?? false)
            ->setAllowedDeliveryTypes(!empty($data[FixedFieldEnum::allowedDeliveryTypes->name]) ? $typeRepository->findBy(["id" => $data[FixedFieldEnum::allowedDeliveryTypes->name]]) : [])
            ->setAllowedCollectTypes(!empty($data[FixedFieldEnum::allowedCollectTypes->name]) ? $typeRepository->findBy(["id" => $data[FixedFieldEnum::allowedCollectTypes->name]]) : [])
            ->setAllowedNatures(!empty($data[FixedFieldEnum::allowedNatures->name]) ? $naturesRepository->findBy(["id" => $data[FixedFieldEnum::allowedNatures->name]]) : [])
            ->setManagers($managers ?? [])
            ->setTemperatureRanges(!empty($data[FixedFieldEnum::allowedTemperatures->name]) ? $temperatureRangeRepository->findBy(["id" => $data[FixedFieldEnum::allowedTemperatures->name]]) : [])
            ->setSignatories($signatories ?? [])
            ->setEmail($data[FixedFieldEnum::email->name] ?? null);

        $location->setProperty(FixedFieldEnum::zone->name, $zone);
        $entityManager->persist($location);

        return $location;
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
            $location = $this->persistLocation([
                FixedFieldEnum::name->name => $label,
            ], $entityManager);
            $entityManager->persist($location);
        }

        return $location;
    }

}
