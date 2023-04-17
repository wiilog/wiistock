<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;

use App\Entity\IOT\SensorMessage;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;
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

    private array $labeledCacheLocations = [];

    public function createEmplacement(array $data, EntityManagerInterface $entityManager): Emplacement
    {
        $typeRepository = $entityManager->getRepository(Type::class);
        $zoneRepository = $entityManager->getRepository(Zone::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $naturesRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $defaultZone = $zoneRepository->findOneBy(['name' => Zone::ACTIVITY_STANDARD_ZONE_NAME]);

        if(!empty($data['zone'])) {
            $zone = $zoneRepository->find($data['zone']) ?? $defaultZone;
        } else {
            $zone = $defaultZone;
        }

        if(!empty($data['signatories'])) {
            $signatoryIds = is_array($data['signatories'])
                ? $data['signatories']
                : Stream::explode(',', $data['signatories'])
                    ->filter()
                    ->map(fn(string $id) => trim($id))
                    ->toArray();

            $signatories = !empty($signatoryIds)
                ? $userRepository->findBy(['id' => $signatoryIds])
                : [];
        }

        $location = (new Emplacement())
            ->setLabel($data["Label"])
            ->setDescription($data["Description"] ?? null)
            ->setIsActive($data["isActive"] ?? true)
            ->setDateMaxTime($data['dateMaxTime'] ?? null)
            ->setIsDeliveryPoint($data["isDeliveryPoint"] ?? null)
            ->setIsOngoingVisibleOnMobile($data["isDeliveryPoint"] ?? false)
            ->setAllowedDeliveryTypes(isset($data['allowedDeliveryTypes']) ? $typeRepository->findBy(["id" => $data["allowedDeliveryTypes"]]) : [])
            ->setAllowedCollectTypes(isset($data['allowedCollectTypes']) ? $typeRepository->findBy(["id" => $data["allowedCollectTypes"]]) : [])
            ->setSignatories($signatories ?? [])
            ->setEmail($data["email"] ?? null)
            ->setZone($zone);


        if (!empty($data['allowed-natures'])) {
            foreach ($data['allowed-natures'] as $allowedNatureId) {
                $location
                    ->addAllowedNature($naturesRepository->find($allowedNatureId));
            }
        }

        if (!empty($data['allowedTemperatures'])) {
            foreach ($data['allowedTemperatures'] as $allowedTemperatureId) {
                $location
                    ->addTemperatureRange($temperatureRangeRepository->find($allowedTemperatureId));
            }
        }

        $entityManager->persist($location);

        return $location;
    }

    public function getEmplacementDataByParams($params = null) {
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

    public function dataRowEmplacement(EntityManagerInterface $manager, Emplacement $emplacement) {
        $url['edit'] = $this->router->generate('emplacement_edit', ['id' => $emplacement->getId()]);

        $sensorMessageRepository = $manager->getRepository(SensorMessage::class);

        $allowedNatures = Stream::from($emplacement->getAllowedNatures())
            ->map(fn(Nature $nature) => $this->formatService->nature($nature))
            ->join(", ");

        $allowedTemperatures = Stream::from($emplacement->getTemperatureRanges())
            ->map(fn(TemperatureRange $temperature) => $temperature->getValue())
            ->join(", ");

        $linkedGroup = $emplacement->getLocationGroup();
        $groupLastMessage = $linkedGroup ?  $sensorMessageRepository->getLastSensorMessage($linkedGroup) : null;
        $locationLastMessage = $sensorMessageRepository->getLastSensorMessage($emplacement);

        $sensorCode = $groupLastMessage && $groupLastMessage->getSensor()->getAvailableSensorWrapper()
            ? $groupLastMessage->getSensor()->getAvailableSensorWrapper()->getName()
            : ($locationLastMessage && $locationLastMessage->getSensor()->getAvailableSensorWrapper()
                ? $locationLastMessage->getSensor()->getAvailableSensorWrapper()->getName()
                : null);

        $hasPairing = !$emplacement->getPairings()->isEmpty() || !$emplacement->getSensorMessages()->isEmpty();

        return [
            'id' => $emplacement->getId(),
            'name' => $emplacement->getLabel() ?: 'Non défini',
            'description' => $emplacement->getDescription() ?: 'Non défini',
            'deliveryPoint' => $emplacement->getIsDeliveryPoint() ? 'oui' : 'non',
            'ongoingVisibleOnMobile' => $emplacement->isOngoingVisibleOnMobile() ? 'oui' : 'non',
            'maxDelay' => $emplacement->getDateMaxTime() ?? '',
            'active' => $emplacement->getIsActive() ? 'actif' : 'inactif',
            'allowedNatures' => $allowedNatures,
            'allowedTemperatures' => $allowedTemperatures,
            'signatories' => $this->formatService->users($emplacement->getSignatories()),
            'email' => $emplacement->getEmail(),
            'zone' => $emplacement->getZone() ? $emplacement->getZone()->getName() : "",
            'actions' => $this->templating->render('emplacement/datatableEmplacementRow.html.twig', [
                'url' => $url,
                'emplacementId' => $emplacement->getId(),
                'location' => $emplacement,
                'linkedGroup' => $linkedGroup,
                'hasPairing' => $hasPairing
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
            $location = $this->createEmplacement([
                "Label" => $label,
            ], $entityManager);
            $entityManager->persist($location);
        }

        return $location;
    }

}
