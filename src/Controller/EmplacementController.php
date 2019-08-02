<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\DimensionsEtiquettes;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\EmplacementRepository;
use App\Repository\LivraisonRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\ReferenceArticleRepository;

use App\Service\UserService;
use App\Service\EmplacementDataService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\ArticleRepository;

/**
 * @Route("/emplacement")
 */
class EmplacementController extends AbstractController
{

    /**
     * @var EmplacementDataService
     */
    private $emplacementDataService;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var DimensionsEtiquettesRepository
     */
    private $dimensionsEtiquettesRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    public function __construct(ReferenceArticleRepository $referenceArticleRepository, DimensionsEtiquettesRepository $dimensionsEtiquettesRepository, EmplacementDataService $emplacementDataService, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, UserService $userService, DemandeRepository $demandeRepository, LivraisonRepository $livraisonRepository, CollecteRepository $collecteRepository, MouvementStockRepository $mouvementStockRepository)
    {
        $this->emplacementDataService = $emplacementDataService;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->collecteRepository = $collecteRepository;
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

    /**
     * @Route("/api", name="emplacement_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->emplacementDataService->getDataForDatatable($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="emplacement_index", methods="GET")
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

		return $this->render('emplacement/index.html.twig', ['emplacement' => $this->emplacementRepository->findAll()]);
    }

    /**
     * @Route("/creer", name="emplacement_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            // on vérifie que l'emplacement n'existe pas déjà
            $emplacementAlreadyExist = $this->emplacementRepository->countByLabel($data['Label']);
            if ($emplacementAlreadyExist) {
                return new JsonResponse(false);
            }

            $em = $this->getDoctrine()->getManager();
            $emplacement = new Emplacement();
            $emplacement
				->setLabel($data["Label"])
				->setDescription($data["Description"])
				->setIsDeliveryPoint($data["isDeliveryPoint"]);
            $em->persist($emplacement);
            $em->flush();
            return new JsonResponse(true);
        }

        throw new NotFoundHttpException("404");
    }

    //    /**
    //     * @Route("/voir", name="emplacement_show", options={"expose"=true},  methods="GET|POST")
    //     */
    //    public function show(Request $request): Response
    //    {
    //        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
    //            $emplacement = $this->emplacementRepository->find($data);
    //
    //            $json = $this->renderView('emplacement/modalShowEmplacementContent.html.twig', [
    //                'emplacement' => $emplacement,
    //            ]);
    //            return new JsonResponse($json);
    //        }
    //        throw new NotFoundHttpException("404");
    //    }

    /**
     * @Route("/api-modifier", name="emplacement_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacement = $this->emplacementRepository->find($data['id']);
            $json = $this->renderView('emplacement/modalEditEmplacementContent.html.twig', [
                'emplacement' => $emplacement,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/edit", name="emplacement_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacement = $this->emplacementRepository->find($data['id']);
            $emplacement
                ->setLabel($data["Label"])
                ->setDescription($data["Description"]);
            $emplacement->setIsDeliveryPoint($data["isDeliveryPoint"]);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verification", name="emplacement_check_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function checkEmplacementCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $emplacementId = json_decode($request->getContent(), true)) {

            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $isUsedBy = $this->isEmplacementUsed($emplacementId);
            if (empty($isUsedBy)) {
                $delete = true;
                $html = $this->renderView('emplacement/modalDeleteEmplacementRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('emplacement/modalDeleteEmplacementWrong.html.twig', [
                    'delete' => false,
                    'isUsedBy' => $isUsedBy
                ]);
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param int $emplacementId
     * @return array
     */
    private function isEmplacementUsed($emplacementId)
    {
        $usedBy = [];

        $demandes = $this->demandeRepository->countByEmplacement($emplacementId);
        if ($demandes > 0) $usedBy[] = 'demandes';

        $livraisons = $this->livraisonRepository->countByEmplacement($emplacementId);
        if ($livraisons > 0) $usedBy[] = 'livraisons';

        $collectes = $this->collecteRepository->countByEmplacement($emplacementId);
        if ($collectes > 0) $usedBy[] = 'collectes';

        $mouvementsStock = $this->mouvementStockRepository->countByEmplacement($emplacementId);
        if ($mouvementsStock > 0) $usedBy[] = 'mouvements de stock';

        $refArticle = $this->referenceArticleRepository->countByEmplacement($emplacementId);
        if ($refArticle > 0)$usedBy[] = 'références article';

        $articles = $this->articleRepository->countByEmplacement($emplacementId);
        if ($articles > 0) $usedBy[] ='articles';

        return $usedBy;
    }

    /**
     * @Route("/supprimer", name="emplacement_delete", options={"expose"=true})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($emplacementId = (int)$data['emplacement']) {

                $emplacement = $this->emplacementRepository->find($emplacementId);

                // on vérifie que l'emplacement n'est plus utilisé
                $usedEmplacement = $this->isEmplacementUsed($emplacementId);

                if (!empty($usedEmplacement)) {
                    return new JsonResponse(false);
                }

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($emplacement);
                $entityManager->flush();
                return new JsonResponse();
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete", name="get_emplacement", options={"expose"=true})
     */
    public function getRefArticles(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::LIST)) {
                return new JsonResponse(['results' => []]);
            }

            $search = $request->query->get('term');

            $emplacement = $this->emplacementRepository->getIdAndLibelleBySearch($search);
            return new JsonResponse(['results' => $emplacement]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-etiquettes", name="emplacement_get_data_to_print", options={"expose"=true})
     */
    public function getDataToPrintLabels(Request $request) : Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $listEmplacements = explode(',', $data['listEmplacements']);

            $emplacementsString = [];
            for ($i = 0; $i < count($listEmplacements); $i++) {
                $emplacementsString[] = $this->emplacementRepository->find($listEmplacements[$i])->getLabel();
            }
            $emplacementsString = array_slice($emplacementsString, $data['start'], $data['length']);
            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            /** @var DimensionsEtiquettes $dimension */
            if ($dimension) {
                $tags['height'] = $dimension->getHeight();
                $tags['width'] = $dimension->getWidth();
                $tags['exists'] = true;
            } else {
                $tags['height'] = $tags['width'] = 0;
                $tags['exists'] = false;
            }
            $data = array('tags' => $tags, 'emplacements' => $emplacementsString);
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/ajax-article-depuis-id", name="get_emplacement_from_id", options={"expose"=true}, methods="GET|POST")
     */
    public function getEmplacementLabelFromId(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $dataContent = json_decode($request->getContent(), true)) {
            $data = [];
            $data['emplacementLabel'] = $this->emplacementRepository->find(intval($dataContent['emplacement']))->getLabel();
            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            if ($dimension && !empty($dimension->getHeight()) && !empty($dimension->getWidth())) {
                $data['height'] = $dimension->getHeight();
                $data['width'] = $dimension->getWidth();
                $data['exists'] = true;
            } else {
                $data['exists'] = false;
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }
}
