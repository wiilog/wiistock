<?php


namespace App\Service;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\ReferenceArticle;
use App\Helper\FormatHelper;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class InvMissionService {

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public Security $security;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public UserService $userService;

    #[Required]
    public FormatService $formatService;

    public function getDataForMissionsDatatable($params = null): array {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_MISSIONS, $this->security->getUser());
        $queryResult = $inventoryMissionRepository->findMissionsByParamsAndFilters($params, $filters);

        $missions = $queryResult['data'];

        $rows = [];
        foreach ($missions as $mission) {
            $rows[] = $this->dataRowMission($mission);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowMission(InventoryMission $mission): array {
        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

		$nbArtInMission = $articleRepository->countByMission($mission);
		$nbRefInMission = $referenceArticleRepository->countByMission($mission);
		$nbEntriesInMission = $inventoryEntryRepository->count(['mission' => $mission]);

        $rateBar = (($nbArtInMission + $nbRefInMission) != 0)
            ? ($nbEntriesInMission * 100 / ($nbArtInMission + $nbRefInMission))
            : 0;

        return [
            'name' => $mission->getName() ?? '',
            'start' => FormatHelper::date($mission->getStartPrevDate()),
            'end' => FormatHelper::date($mission->getEndPrevDate()),
            'anomaly' => $inventoryMissionRepository->countAnomaliesByMission($mission) > 0,
            'rate' => $this->templating->render('inventaire/datatableMissionsBar.html.twig', [
                'rateBar' => $rateBar,
            ]),
            'type' => $mission->getType() ?? '',
            'delete' => $this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)
                ? $this->templating->render('datatable/trash.html.twig', [
                    'id' => $mission->getId(),
                    'modal' => '#modalDeleteMission',
                    'route' => 'mission_check_delete',
                    'submit' => '#submitDeleteMission',
                    'color' => 'black'
                ]) : [],
            'actions' => $this->templating->render('inventaire/datatableMissionsRow.html.twig', [
                'id' => $mission->getId(),
            ]),
        ];
    }

    public function getDataForOneMissionDatatable(InventoryMission $mission,
                                                  ParameterBag     $params = null,
                                                                   $isArticle = true): array {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_SHOW_MISSION, $this->security->getUser());

        $queryResult = $isArticle
            ? $inventoryMissionRepository->findArtByMissionAndParamsAndFilters($mission, $params, $filters)
            : $inventoryMissionRepository->findRefByMissionAndParamsAndFilters($mission, $params, $filters);

        $refArray = $queryResult['data'];

        $rows = [];
        foreach ($refArray as $data) {
            $rows[] = $isArticle
                ? $this->dataRowArtMission($data, $mission)
                : $this->dataRowRefMission($data, $mission);
        }

        $index = intval($params->all('order')[0]['column']);
        if ($rows) {
            $columnName = array_keys($rows[0])[$index];
            $column = array_column($rows, $columnName);
            array_multisort($column, $params->all('order')[0]['dir'] === "asc" ? SORT_ASC : SORT_DESC, $rows);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function getDataForOneLocationMissionDatatable(EntityManagerInterface  $entityManager,
                                                          InventoryMission        $mission,
                                                          ParameterBag            $params = null) {
        $inventoryLocationMissionRepository = $entityManager->getRepository(InventoryLocationMission::class);
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_SHOW_MISSION, $this->security->getUser());

        return $inventoryLocationMissionRepository->getDataByMission($mission, $params, $filters);
    }

    public function dataRowRefMission(ReferenceArticle $ref, InventoryMission $mission): array {
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);

        $inventoryEntry = $inventoryEntryRepository->findOneBy(['refArticle' => $ref, 'mission' => $mission]);
        $refDateAndQuantity = $inventoryEntryRepository->getEntryByMissionAndRefArticle($mission, $ref);

        $row = $this->dataRowMissionArtRef(
            $ref->getEmplacement(),
            $ref->getReference(),
            $ref->getBarCode(),
            $ref->getLibelle(),
            (!empty($refDateAndQuantity) && isset($refDateAndQuantity['date'])) ? $refDateAndQuantity['date'] : null,
            $inventoryEntryRepository->countInventoryAnomaliesByRef($ref) > 0 ? 'oui' : ($refDateAndQuantity ? 'non' : '-'),
            $ref->getQuantiteStock(),
            (!empty($refDateAndQuantity) && isset($refDateAndQuantity['quantity'])) ? $refDateAndQuantity['quantity'] : null,
        );

        $actionData = [
            "referenceId" => $ref->getId(),
            "missionId" => $mission->getId(),
        ];

        if ($inventoryEntry) {
            $actionData['inventoryEntryId'] = $inventoryEntry->getId();
        }

        $row['Actions'] = $this->templating->render('saisie_inventaire/inventoryEntryRefArticleRow.html.twig', [
            'inventoryData' => Stream::from($actionData)
                ->map(fn(string $value, string $key) => "${key}: ${value}")
                ->join(';'),
        ]);

        return $row;
    }

	/**
	 * @param Article $art
	 * @param InventoryMission $mission
	 * @return array
     */
    public function dataRowArtMission($art, $mission) {
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);

        $artDateAndQuantity = $inventoryEntryRepository->getEntryByMissionAndArticle($mission, $art);
        return $this->dataRowMissionArtRef(
            $art->getEmplacement(),
            $art->getArticleFournisseur()->getReferenceArticle(),
            $art->getBarCode(),
            $art->getlabel(),
            !empty($artDateAndQuantity) ? $artDateAndQuantity['date'] : null,
            $inventoryEntryRepository->countInventoryAnomaliesByArt($art) > 0 ? 'oui' : ($artDateAndQuantity ? 'non' : '-'),
            $art->getQuantite(),
            (!empty($artDateAndQuantity) && isset($artDateAndQuantity['quantity'])) ? $artDateAndQuantity['quantity'] : null,
            $art->getCurrentLogisticUnit()
        );
    }

    private function dataRowMissionArtRef(?Emplacement       $emplacement,
                                          ?string            $reference,
                                          ?string            $codeBarre,
                                          ?string            $label,
                                          ?DateTimeInterface $date,
                                          ?string            $anomaly,
                                          ?string            $quantiteStock,
                                          ?string            $quantiteComptee,
                                          ?Pack              $pack = null): array {
        if ($emplacement) {
            $location = $emplacement->getLabel();
            $emptyLocation = false;
        }
        else {
            $location = '<i class="fas fa-exclamation-triangle red" title="Aucun emplacement défini : n\'apparaîtra sur le nomade."></i>';
            $emptyLocation = true;
        }

        return [
            'Ref' => $reference,
            'CodeBarre' => $codeBarre,
            'Label' => $label,
            'UL' => $pack ? $pack->getCode() : '',
            'Location' => $location,
            'Date' => isset($date) ? $date->format('d/m/Y') : '',
            'Anomaly' => $anomaly,
            'QuantiteStock' => $quantiteStock,
            'QuantiteComptee' => $quantiteComptee,
            'EmptyLocation' => $emptyLocation,
        ];
    }
}
