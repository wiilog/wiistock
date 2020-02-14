<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\InventoryMission;

use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;

use App\Service\InventoryService;
use App\Service\InvMissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Service\UserService;

use DateTime;


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

    /**
     * @var InvMissionService
     */
    private $invMissionService;

	/**
	 * @var InventoryService
	 */
    private $inventoryService;

    public function __construct(
    	InventoryMissionRepository $inventoryMissionRepository,
		UserService $userService,
		InventoryEntryRepository $inventoryEntryRepository,
		ReferenceArticleRepository $referenceArticleRepository,
		ArticleRepository $articleRepository,
		InvMissionService $invMissionService,
		InventoryService $inventoryService
	)
    {
        $this->userService = $userService;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->invMissionService = $invMissionService;
        $this->inventoryService = $inventoryService;
    }

    /**
     * @Route("/", name="inventory_mission_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->invMissionService->getDataForMissionsDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="mission_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($data['startDate'] > $data['endDate'])
                return new JsonResponse(false);

            $em = $this->getDoctrine()->getManager();

            $mission = new InventoryMission();
            $mission
                ->setStartPrevDate(DateTime::createFromFormat('Y-m-d', $data['startDate']))
                ->setEndPrevDate(DateTime::createFromFormat('Y-m-d', $data['endDate']));

            $em->persist($mission);
            $em->flush();

            return new JsonResponse(true);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verification", name="mission_check_delete", options={"expose"=true})
     */
    public function checkUserCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $missionId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_UTIL)) {
                return $this->redirectToRoute('access_denied');
            }

            $missionArt = $this->inventoryMissionRepository->countArtByMission($missionId);
            $missionRef = $this->inventoryMissionRepository->countRefArtByMission($missionId);

            if ($missionArt != 0 || $missionRef != 0)
                $missionIsUsed = false;
            else
                $missionIsUsed = true;

            if ($missionIsUsed == true) {
                $delete = true;
                $html = $this->renderView('inventaire/modalDeleteMissionRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('inventaire/modalDeleteMissionWrong.html.twig');
            }
            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="mission_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $mission = $this->inventoryMissionRepository->find($data['missionId']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($mission);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir/{id}", name="inventory_mission_show", options={"expose"=true}, methods="GET|POST")
     */
    public function show(InventoryMission $mission)
    {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
                return $this->redirectToRoute('access_denied');
            }

            return $this->render('inventaire/show.html.twig', [
                'missionId' => $mission->getId()
            ]);
    }

    /**
     * @Route("/donnees/api/{id}", name="inv_entry_api", options={"expose"=true}, methods="GET|POST")
     */
    public function entryApi(InventoryMission $mission, Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
            return $this->redirectToRoute('access_denied');
        }

        $data = $this->invMissionService->getDataForOneMissionDatatable($mission, $request->request);
        return new JsonResponse($data);
    }

    /**
     * @Route("/ajouter", name="add_to_mission", options={"expose"=true}, methods="GET|POST")
     */
    public function addToMission(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();

            $mission = $this->inventoryMissionRepository->find($data['missionId']);

            foreach ($data['articles'] as $articleId) {
                $article = $this->articleRepository->find($articleId);

				$alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($article, $mission, false);
				if ($alreadyInMission) return new JsonResponse(false);

                $article->addInventoryMission($mission);
                $em->persist($mission);
                $em->flush();
            }

            foreach ($data['refArticles'] as $refArticleId) {
                $refArticle = $this->referenceArticleRepository->find($refArticleId);

				$alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($refArticle, $mission, true);
				if ($alreadyInMission) return new JsonResponse(false);

				$refArticle->addInventoryMission($mission);
                $em->persist($mission);
                $em->flush();
            }

            return new JsonResponse();
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/mission-infos", name="get_mission_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
        	$mission = $this->inventoryMissionRepository->find($data['param']);

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
