<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;

use App\Entity\Nature;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;

use Twig\Environment as Twig_Environment;

class EmplacementDataService {

    const PAGE_EMPLACEMENT = 'emplacement';
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    private $security;

    private $entityManager;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security) {

        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->security = $security;
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
        $allowedNatures = implode(
            ';',
            array_map(
                function(Nature $nature) {
                    return $nature->getLabel();
                },
                $emplacement->getAllowedNatures()->toArray()
            )
        );

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

}
