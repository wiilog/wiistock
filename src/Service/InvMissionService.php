<?php


namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\InventoryMission;

use App\Repository\FiltreSupRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;
use App\Repository\InventoryMissionRepository;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

class InvMissionService
{
    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

	/**
	 * @var FiltreSupRepository
	 */
    private $filtreSupRepository;

	/**
	 * @var InventoryEntryRepository
	 */
    private $inventoryEntryRepository;

	/**
	 * @var Security
	 */
    private $security;

    private $em;

    public function __construct(InventoryEntryRepository $inventoryEntryRepository, Security $security, FiltreSupRepository $filtreSupRepository, RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository, InventoryMissionRepository $inventoryMissionRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->security = $security;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
    }

	/**
	 * @param array|null $params
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function getDataForMissionsDatatable($params = null)
	{
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_MISSIONS, $this->security->getUser());

		$queryResult = $this->inventoryMissionRepository->findMissionsByParamsAndFilters($params, $filters);

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

	/**
	 * @param InventoryMission $mission
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
	public function dataRowMission($mission)
	{
		$nbArtInMission = $this->articleRepository->countByMission($mission);
		$nbRefInMission = $this->referenceArticleRepository->countByMission($mission);
		$nbEntriesInMission = $this->inventoryEntryRepository->countByMission($mission);

		$rateBar = ($nbArtInMission + $nbRefInMission) != 0 ? $nbEntriesInMission * 100 / ($nbArtInMission + $nbRefInMission) : 0;

		$row =
			[
				'StartDate' => $mission->getStartPrevDate() ? $mission->getStartPrevDate()->format('d/m/Y') : '',
				'EndDate' => $mission->getEndPrevDate() ? $mission->getEndPrevDate()->format('d/m/Y') : '',
				'Anomaly' => $this->inventoryMissionRepository->countAnomaliesByMission($mission) > 0,
				'Rate' => $this->templating->render('inventaire/datatableMissionsBar.html.twig', [
					'rateBar' => $rateBar
				]),
				'Actions' => $this->templating->render('inventaire/datatableMissionsRow.html.twig', [
					'missionId' => $mission->getId(),
				]),
			];
		return $row;
	}

    public function getDataForOneMissionDatatable($mission, $params = null)
    {
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_SHOW_MISSION, $this->security->getUser());

		$queryResultRef = $this->inventoryMissionRepository->findRefByMissionAndParamsAndFilters($mission, $params, $filters);
        $queryResultArt = $this->inventoryMissionRepository->findArtByMissionAndParamsAndFilters($mission, $params, $filters);

        $refArray = $queryResultRef['data'];
        $artArray = $queryResultArt['data'];

        $rows = [];
        foreach ($refArray as $ref) {
            $rows[] = $this->dataRowRefMission($ref, $mission);
        }
        foreach ($artArray as $art) {
            $rows[] = $this->dataRowArtMission($art, $mission);
        }
        $index = intval($params->get('order')[0]['column']);
        if ($rows) {
        	$columnName = array_keys($rows[0])[$index];
        	$column = array_column($rows, $columnName);
        	array_multisort($column, $params->get('order')[0]['dir'] === "asc" ? SORT_ASC : SORT_DESC, $rows);
		}
        return [
            'data' => $rows,
            'recordsTotal' => $queryResultRef['total'] + $queryResultArt['total'],
            'recordsFiltered' => $queryResultRef['count'] + $queryResultArt['count'],
        ];
    }

    public function dataRowRefMission($ref, $mission)
    {
        $refDate = $this->referenceArticleRepository->getEntryDateByMission($mission, $ref);

        $row =
            [
                'Ref' => $ref->getReference(),
                'Label' => $ref->getLibelle(),
                'Date' => $refDate ? $refDate['date']->format('d/m/Y') : '',
                'Anomaly' => $this->referenceArticleRepository->countInventoryAnomaliesByRef($ref) > 0 ? 'oui' : ($refDate ? 'non' : '-')
            ];
        return $row;
    }

    public function dataRowArtMission($art, $mission)
    {
        $artDate = $this->articleRepository->getEntryDateByMission($mission, $art);

        $row =
            [
                'Ref' => $art->getReference(),
                'Label' => $art->getlabel(),
                'Date' => $artDate ? $artDate['date']->format('d/m/Y') : '',
                'Anomaly' => $this->articleRepository->countInventoryAnomaliesByArt($art) > 0 ? 'oui' : ($artDate ? 'non' : '-')
            ];
        return $row;
    }
}