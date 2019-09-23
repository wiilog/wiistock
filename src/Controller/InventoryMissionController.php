<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\InventoryMission;
use App\Entity\InventoryEntry;
use App\Entity\ReferenceArticle;
use App\Entity\Article;

use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;

use phpDocumentor\Reflection\Types\String_;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Service\UserService;


/**
 * @Route("/inventaire/mission")
 */
class InventoryMissionController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

    /**
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    public function __construct(UserService $userService, InventoryMissionRepository $inventoryMissionRepository, InventoryEntryRepository $inventoryEntryRepository, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository)
    {
        $this->userService = $userService;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
    }

    /**
     * @Route("/", name="inventory_mission_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('mission_inventaire/index.html.twig', [

        ]);
    }

    /**
     * @Route("/api", name="invMissions_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $missions = $this->inventoryMissionRepository->findAll();
            $rows = [];
            foreach ($missions as $mission) {
                $rows[] =
                    [
                        'StartDate' => $mission->getStartPrevDate()->format('d/m/Y'),
                        'EndDate' => $mission->getEndPrevDate()->format('d/m/Y'),
                        'Actions' => $this->renderView('mission_inventaire/datatableMissionsRow.html.twig', [
                            'missionId' => $mission->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="entry_index", options={"expose"=true}, methods="GET|POST")
     */
    public function entry_index(InventoryMission $mission)
    {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            return $this->render('mission_inventaire/entry_index.html.twig', [
                'missionId' => $mission->getId(),
            ]);
    }

    /**
     * @Route("/donnees/api/{id}", name="invEntry_api", options={"expose"=true}, methods="GET|POST")
     */
    public function entryApi(InventoryMission $mission)
    {
        if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $refArray = $this->referenceArticleRepository->getByMission($mission);
        $artArray = $this->articleRepository->getByMission($mission);

        $rows = [];
        foreach ($refArray as $ref) {
            $refDate = null;
            if ($ref['date'] != null)
               $refDate = $ref['date']->format('d/m/Y');
            $rows[] =
                [
                    'Article' => $ref['libelle'],
                    'Date' => $refDate,
                    'Anomaly' => 'test'
                ];
        }
        foreach ($artArray as $article) {
            $artDate = null;
            if ($article['date'] != null)
                $artDate = $article['date']->format('d/m/Y');
            $rows[] =
                [
                    'Article' => $article['label'],
                    'Date' => $artDate,
                    'Anomaly' => 'test'
                ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }
}