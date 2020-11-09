<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\InventoryEntry;
use App\Entity\Menu;
use App\Entity\InventoryMission;

use App\Entity\ReferenceArticle;
use App\Helper\FormatHelper;
use App\Helper\Stream;
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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="mission_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($data['startDate'] > $data['endDate'])
                return new JsonResponse([
                    'success' => false,
                    'msg' => "La date de début doit être antérieure à celle de fin."
                ]);

            $em = $this->getDoctrine()->getManager();

            $mission = new InventoryMission();
            $mission
                ->setStartPrevDate(DateTime::createFromFormat('Y-m-d', $data['startDate']))
                ->setEndPrevDate(DateTime::createFromFormat('Y-m-d', $data['endDate']));

            $em->persist($mission);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La mission d\'inventaire a bien été créée.'
            ]);
        }
        throw new BadRequestHttpException();
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
        throw new BadRequestHttpException();
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
        throw new BadRequestHttpException();
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

            $articlesAndRefs = explode(' ', $data['articles']);
            foreach ($articlesAndRefs as $barcode) {
                $barcode = trim($barcode);
                $articleOrRef = $articleRepository->findOneBy([
                    'barCode' => $barcode
                ]);

                if (!isset($articleOrRef)) {
                    $articleOrRef = $referenceArticleRepository->findOneBy([
                        'barCode' => $barcode
                    ]);
                }

                $checkForArt = $articleOrRef instanceof Article
                    && $articleOrRef->getArticleFournisseur()->getReferenceArticle()->getStatut()->getNom() === ReferenceArticle::STATUT_ACTIF
                    && !$this->inventoryService->isInMissionInSamePeriod($articleOrRef, $mission, false);

                $checkForRef = $articleOrRef instanceof ReferenceArticle
                    && $articleOrRef->getStatut()->getNom() === ReferenceArticle::STATUT_ACTIF
                    && !$this->inventoryService->isInMissionInSamePeriod($articleOrRef, $mission, true);

                if ($checkForArt || $checkForRef) {
                    $articleOrRef->addInventoryMission($mission);
                }
            }
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
            ]);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/mission-infos", name="get_mission_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @return Response
     */
    public function getCSVForInventoryMission(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mission = $this->inventoryMissionRepository->find($data['param']);

            /** @var InventoryEntry[] $inventoryEntries */
            $inventoryEntries = Stream::from($mission->getEntries()->toArray())
                ->reduce(function (array $carry, InventoryEntry $entry) {
                    $article = $entry->getArticle();
                    $refArticle = $entry->getRefArticle();

                    if (isset($article)) {
                        $barcode = $article->getBarCode();
                        $carry[$barcode] = $entry;
                    }

                    if (isset($refArticle)) {
                        $barcode = $refArticle->getBarCode();
                        $carry[$barcode] = $entry;
                    }
                    return $carry;
                }, []);

            $articles = $mission->getArticles();
            $refArticles = $mission->getRefArticles();
            $missionStartDate = $mission->getStartPrevDate();
            $missionEndDate = $mission->getEndPrevDate();

            $missionHeader = ['MISSION DU ' . $missionStartDate->format('d/m/Y') . ' AU ' . $missionEndDate->format('d/m/Y')];
            $headers = [
                'référence',
                'label',
                'quantité',
                'emplacement',
                'date dernier inventaire',
                'anomalie'
            ];

            $data = [];
            $data[] = $missionHeader;
            $data[] = $headers;

            /** @var Article $article */
            foreach ($articles as $article) {
                $articleData = [];
                $barcode = $article->getBarCode();

                $articleFournisseur = $article->getArticleFournisseur();
                $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;

                $articleData[] = $referenceArticle ? $referenceArticle->getReference() : '';
                $articleData[] = $referenceArticle ? $referenceArticle->getLibelle() : '';
                $articleData[] = $article->getQuantite() ?? '';
                $articleData[] = FormatHelper::location($article->getEmplacement());
                if (isset($inventoryEntries[$barcode])) {
                    $articleData[] = FormatHelper::date($inventoryEntries[$barcode]->getDate());
                    $articleData[] = FormatHelper::bool($inventoryEntries[$barcode]->getAnomaly());
                }

                $data[] = $articleData;
            }

            /** @var ReferenceArticle $refArticle */
            foreach ($refArticles as $refArticle) {
                $refArticleData = [];
                $barcode = $refArticle->getBarCode();

                $refArticleData[] = $refArticle->getReference() ?? '';
                $refArticleData[] = $refArticle->getLibelle() ?? '';
                $refArticleData[] = $refArticle->getQuantiteStock() ?? '';
                $refArticleData[] = FormatHelper::location($refArticle->getEmplacement());
                if (isset($inventoryEntries[$barcode])) {
                    $refArticleData[] = FormatHelper::date($inventoryEntries[$barcode]->getDate());
                    $refArticleData[] = FormatHelper::bool($inventoryEntries[$barcode]->getAnomaly());
                }

                $data[] = $refArticleData;
            }

            return new JsonResponse($data);
        } else {
            throw new BadRequestHttpException();
        }
    }
}
