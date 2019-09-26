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

    public function __construct(InventoryMissionRepository $inventoryMissionRepository, UserService $userService, InventoryEntryRepository $inventoryEntryRepository, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository)
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

        return $this->render('inventaire/index.html.twig');
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

                $artRate = $this->articleRepository->countByMission($mission);
                $refRate = $this->referenceArticleRepository->countByMission($mission);
                $rateMin = (int)$refRate['entryRef'] + (int)$artRate['entryArt'];
                $rateMax = (int)$refRate['ref'] + (int)$artRate['art'];
                $rateBar = $rateMax !== 0 ? $rateMin * 100 / $rateMax : 0;
                $rows[] =
                    [
                        'StartDate' => $mission->getStartPrevDate()->format('d/m/Y'),
                        'EndDate' => $mission->getEndPrevDate()->format('d/m/Y'),
                        'Anomaly' => $anomaly != 0,
                        'Rate' => $this->renderView('inventaire/datatableMissionsBar.html.twig', [
                            'rateBar' => $rateBar
                        ]),
                        'Actions' => $this->renderView('inventaire/datatableMissionsRow.html.twig', [
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
     * @Route("/voir/{id}", name="inventory_mission_show", options={"expose"=true}, methods="GET|POST")
     */
    public function show(InventoryMission $mission)
    {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            return $this->render('inventaire/show.html.twig', [
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
                    'Label' => $ref['libelle'],
                    'Ref' => $ref['reference'],
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
                    'Label' => $article['label'],
                    'Ref' => $article['reference'],
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
        	$mission = $this->inventoryMissionRepository->find($data['missionId']);

            $articles = $mission->getArticles();
            $refArticles = $mission->getRefArticles();
            $missionStartDate = $mission->getStartPrevDate();
            $missionEndDate = $mission->getEndPrevDate();

            $missionHeader = ['MISSION DU ' . $missionStartDate->format('d/m/Y') . ' AU ' . $missionEndDate->format('d/m/Y')];
            $headers = ['référence', 'label', 'quantité', 'emplacement'];

            $data = [];
            $data[] = $missionHeader;
            $data[] = $headers;

            foreach ($articles as $article) {
                $articleData = [];

                $articleData[] = $article->getReference();
                $articleData[] = $article->getLabel();
                $articleData[] = $article->getQuantite();
                $articleData[] = $article->getEmplacement()->getLabel();

                $data[] = $articleData;
            }

            foreach ($refArticles as $refArticle) {
                $refArticleData = [];

                $refArticleData[] = $refArticle->getReference();
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