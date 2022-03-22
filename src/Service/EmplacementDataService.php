<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;

use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;

use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class EmplacementDataService {

    const PAGE_EMPLACEMENT = 'emplacement';

    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public Security $security;

    /** @Required */
    public EntityManagerInterface $entityManager;

    private array $labeledCacheLocations = [];

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
            $rows[] = $this->dataRowEmplacement($emplacement);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
            'listId' => $emplacementsString,
        ];
    }

    public function dataRowEmplacement(Emplacement $emplacement) {
        $url['edit'] = $this->router->generate('emplacement_edit', ['id' => $emplacement->getId()]);

        $allowedNatures = Stream::from($emplacement->getAllowedNatures())
            ->map(fn(Nature $nature) => $nature->getLabel())
            ->join(", ");

        $allowedTemperatures = Stream::from($emplacement->getTemperatureRanges())
            ->map(fn(TemperatureRange $temperature) => $temperature->getValue())
            ->join(", ");

        $linkedGroup = $emplacement->getLocationGroup();
        $groupLastMessage = $linkedGroup ? $linkedGroup->getLastMessage() : null;
        $locationLastMessage = $emplacement->getLastMessage();

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
            $location = new Emplacement();
            $location->setLabel($label);
            $entityManager->persist($location);
        }

        return $location;
    }

}
