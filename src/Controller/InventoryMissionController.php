<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\InventoryMission;

use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;

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
     * @Route("/api", name="inv_missions_api", options={"expose"=true}, methods="GET|POST")
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
                $anomaly = $this->inventoryMissionRepository->countByMissionAnomaly($mission);
                if ($anomaly != 0)
                    $anomalyRow = true;
                else
                    $anomalyRow = false;
                $rows[] =
                    [
                        'StartDate' => $mission->getStartPrevDate()->format('d/m/Y'),
                        'EndDate' => $mission->getEndPrevDate()->format('d/m/Y'),
                        'Anomaly' => $anomalyRow,
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

            return $this->render('mission_inventaire/show.html.twig', [
                'missionId' => $mission->getId(),
            ]);
    }

    /**
     * @Route("/donnees/api/{id}", name="inv_entry_api", options={"expose"=true}, methods="GET|POST")
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
                    'Anomaly' => $ref['hasInventoryAnomaly'] ? 'oui' : 'non'
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
                    'Anomaly' => $article['hasInventoryAnomaly'] ? 'oui' : 'non'
                ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }


    /**
     * @Route("/mission-infos", name="get_mission_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articles = $this->referenceArticleRepository->getArticlesByMissionId($data['missionId']);
            $refArticles = $this->referenceArticleRepository->getRefArticlesByMissionId($data['missionId']);
            $missionDate = $this->inventoryMissionRepository->getDatesByMission($data['missionId']);

            $missionHeader = [];
            $missionHeader = array_merge($missionHeader, [$missionDate[0]['startPrevDate']->format('d/m/Y'), $missionDate[0]['endPrevDate']->format('d/m/Y')]);

            $headers = [];
            $headers = array_merge($headers, ['label', 'quantité', 'emplacement']);

            $data = [];
            $data[] = $missionHeader;
            $data[] = $headers;

            foreach ($articles as $article) {
                $articleData = [];

                $articleData[] = $article->getLabel();
                $articleData[] = $article->getQuantite();
                $articleData[] = $article->getEmplacement()->getLabel();

                $data[] = $articleData;
            }

            foreach ($refArticles as $refArticle) {
                $refArticleData = [];

                $refArticleData[] = $refArticle->getLibelle();
                $refArticleData[] = $refArticle->getQuantiteStock();
                $refArticleData[] = $refArticle->getEmplacement()->getLabel();

                $data[] = $refArticleData;
            }

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}