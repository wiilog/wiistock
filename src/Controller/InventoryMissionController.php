<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Menu;
use App\Entity\InventoryMission;

use App\Entity\ReferenceArticle;
use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;

use App\Service\InventoryService;
use App\Service\InvMissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Service\UserService;

use DateTime;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


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
		InvMissionService $invMissionService,
		InventoryService $inventoryService
	)
    {
        $this->userService = $userService;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
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
     * @param Request $request
     * @param InvMissionService $invMissionService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request,
                        InvMissionService $invMissionService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $invMissionService->getDataForMissionsDatatable($request->request);

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
	 * @param Request $request
	 * @param InventoryEntryRepository $entryRepository
	 * @return Response
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function checkMissionCanBeDeleted(Request $request, InventoryEntryRepository $entryRepository): Response
    {
        if ($request->isXmlHttpRequest() && $missionId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $missionArt = $this->inventoryMissionRepository->countArtByMission($missionId);
            $missionRef = $this->inventoryMissionRepository->countRefArtByMission($missionId);
            $missionEntries = $entryRepository->countByMission($missionId);

            $missionIsUsed = (intval($missionArt) + intval($missionRef) + intval($missionEntries) > 0);

            if ($missionIsUsed) {
                $delete = false;
                $html = $this->renderView('inventaire/modalDeleteMissionWrong.html.twig');
            } else {
                $delete = true;
                $html = $this->renderView('inventaire/modalDeleteMissionRight.html.twig');
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
            $mission = $this->inventoryMissionRepository->find(intval($data['missionId']));
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($mission);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir/{id}", name="inventory_mission_show", options={"expose"=true}, methods="GET|POST")
     * @param InventoryMission $mission
     * @return RedirectResponse|Response
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
     * @Route("/donnees_article/api/{id}", name="inv_entry_article_api", options={"expose"=true}, methods="GET|POST")
     * @param InventoryMission $mission
     * @param Request $request
     * @return Response
     */
    public function entryApiArticle(InventoryMission $mission,
                                    Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
            return $this->redirectToRoute('access_denied');
        }

        $data = $this->invMissionService->getDataForOneMissionDatatable($mission, $request->request, true);
        return new JsonResponse($data);
    }

    /**
     * @Route("/donnees_reference_article/api/{id}", name="inv_entry_reference_article_api", options={"expose"=true}, methods="GET|POST")
     * @param InventoryMission $mission
     * @param Request $request
     * @return Response
     */
    public function entryApiReferenceArticle(InventoryMission $mission,
                                             Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
            return $this->redirectToRoute('access_denied');
        }

        $data = $this->invMissionService->getDataForOneMissionDatatable($mission, $request->request, false);
        return new JsonResponse($data);
    }

    /**
     * @Route("/ajouter", name="add_to_mission", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function addToMission(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
                return $this->redirectToRoute('access_denied');
            }

            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $articleRepository = $entityManager->getRepository(Article::class);

            $mission = $this->inventoryMissionRepository->find($data['missionId']);

            foreach ($data['articles'] as $articleId) {
                $article = $articleRepository->find($articleId);

				$alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($article, $mission, false);
				if ($alreadyInMission) return new JsonResponse(false);

                $article->addInventoryMission($mission);
                $entityManager->persist($mission);
                $entityManager->flush();
            }

            foreach ($data['refArticles'] as $refArticleId) {
                $refArticle = $referenceArticleRepository->find($refArticleId);

				$alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($refArticle, $mission, true);
				if ($alreadyInMission) return new JsonResponse(false);

				$refArticle->addInventoryMission($mission);
                $entityManager->persist($mission);
                $entityManager->flush();
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
