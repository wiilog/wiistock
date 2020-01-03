<?php


namespace App\Service;

use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;
use App\Repository\InventoryMissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;

class InvMissionService
{
    /**
     * @var Twig_Environment
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

    private $em;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ArticleRepository $articleRepository,
                                InventoryMissionRepository $inventoryMissionRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
    }

    public function getDataForDatatable($mission, $params = null)
    {

        $queryResultRef = $this->inventoryMissionRepository->findRefByParamsAndMission($mission, $params);
        $queryResultArt = $this->inventoryMissionRepository->findArtByParamsAndMission($mission, $params);

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
        if ($refDate != null)
            $refDate = $refDate['date']->format('d/m/Y');

        $row =
            [
                'Ref' => $ref->getReference(),
                'Label' => $ref->getLibelle(),
                'Date' => $refDate,
                'Anomaly' => $this->referenceArticleRepository->countInventoryAnomaliesByRef($ref) > 0 ? 'oui' : 'non'
            ];
        return $row;
    }

    public function dataRowArtMission($art, $mission)
    {
        $artDate = $this->articleRepository->getEntryDateByMission($mission, $art);
        if ($artDate != null)
            $artDate = $artDate['date']->format('d/m/Y');
        $row =
            [
                'Ref' => $art->getReference(),
                'Label' => $art->getlabel(),
                'Date' => $artDate,
                'Anomaly' => $this->articleRepository->countInventoryAnomaliesByArt($art) > 0 ? 'oui' : 'non'
            ];
        return $row;
    }
}
