<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Repository\ArrivageRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ReceptionRepository;
use App\Repository\ReceptionReferenceArticleRepository;
use App\Service\UserService;
use App\Service\FournisseurDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/fournisseur")
 */
class FournisseurController extends AbstractController
{
    /**
     * @var ReceptionReferenceArticleRepository
     */
    private $receptionReferenceArticleRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var FournisseurDataService
     */
    private $fournisseurDataService;

    /**
     * @var ArrivageRepository
     */
    private $arrivageRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(ArrivageRepository $arrivageRepository, FournisseurDataService $fournisseurDataService,ReceptionReferenceArticleRepository $receptionReferenceArticleRepository, ReceptionRepository $receptionRepository, ArticleFournisseurRepository $articleFournisseurRepository, FournisseurRepository $fournisseurRepository, UserService $userService)
    {
        $this->arrivageRepository = $arrivageRepository;
        $this->fournisseurDataService = $fournisseurDataService;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->userService = $userService;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->receptionRepository = $receptionRepository;
        $this->receptionReferenceArticleRepository = $receptionReferenceArticleRepository;
    }

    /**
     * @Route("/api", name="fournisseur_api", options={"expose"=true}, methods="POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_FOUR)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->fournisseurDataService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="fournisseur_index", methods="GET")
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_FOUR)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('fournisseur/index.html.twig', ['fournisseur' => $this->fournisseurRepository->findAll()]);
    }

    /**
     * @Route("/creer", name="fournisseur_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
			// unicité du code fournisseur
			$codeAlreadyUsed = intval($this->fournisseurRepository->countByCode($data['Code']));

			if ($codeAlreadyUsed) {
				return new JsonResponse([
					'success' => false,
					'msg' => "Ce code fournisseur est déjà utilisé.",
				]);
			}

            $em = $this->getDoctrine()->getManager();
            $fournisseur = new Fournisseur();
            $fournisseur
				->setNom($data["Nom"])
				->setCodeReference($data["Code"]);
            $em->persist($fournisseur);
            $em->flush();

			return new JsonResponse(['success' => true, 'id' => $fournisseur->getId(), 'text' => $fournisseur->getNom()]);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="fournisseur_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseur = $this->fournisseurRepository->find($data['id']);
            $json = $this->renderView('fournisseur/modalEditFournisseurContent.html.twig', [
                'fournisseur' => $fournisseur,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="fournisseur_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseur = $this->fournisseurRepository->find($data['id']);
            $fournisseur
                ->setNom($data['nom'])
                ->setCodeReference($data['codeReference']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/verification", name="fournisseur_check_delete", options={"expose"=true})
     */
    public function checkFournisseurCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $fournisseurId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_FOUR)) {
                return $this->redirectToRoute('access_denied');
            }

            $isUsedBy = $this->isFournisseurUsed($fournisseurId);

            if (empty($isUsedBy)) {
                $delete = true;
                $html = $this->renderView('fournisseur/modalDeleteFournisseurRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('fournisseur/modalDeleteFournisseurWrong.html.twig', [
                	'delete' => false,
					'isUsedBy' => $isUsedBy
				]);
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

	/**
	 * @param int $fournisseurId
	 * @return array
	 */
    private function isFournisseurUsed($fournisseurId)
    {
    	$usedBy = [];

    	$AF = $this->articleFournisseurRepository->countByFournisseur($fournisseurId);
    	if ($AF > 0) $usedBy[] = 'articles fournisseur';

    	$receptions = $this->receptionRepository->countByFournisseur($fournisseurId);
    	if ($receptions > 0) $usedBy[] = 'réceptions';

		$ligneReceptions = $this->receptionReferenceArticleRepository->countByFournisseurId($fournisseurId);
		if ($ligneReceptions > 0) $usedBy[] = 'lignes réception';

		$arrivages = $this->arrivageRepository->countByFournisseur($fournisseurId);
		if ($arrivages > 0) $usedBy[] = 'arrivages';

        // $mouvements = $this->mouvementRepository->countByFournisseur($fournisseurId);
//		if ($mouvements > 0) $usedBy[] = 'mouvements';

        return $usedBy;
    }


    /**
     * @Route("/supprimer", name="fournisseur_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            if ($fournisseurId = (int)$data['fournisseur']) {

                $fournisseur = $this->fournisseurRepository->find($fournisseurId);

                // on vérifie que le fournisseur n'est plus utilisé
                $usedFournisseur = $this->isFournisseurUsed($fournisseurId);

                if (!empty($usedFournisseur)) {
                    return new JsonResponse(false);
                }

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($fournisseur);
                $entityManager->flush();
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete", name="get_fournisseur", options={"expose"=true})
     */
    public function getFournisseur(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $fournisseur = $this->fournisseurRepository->getIdAndLibelleBySearch($search);

            return new JsonResponse(['results' => $fournisseur]);
        }
        throw new NotFoundHttpException("404");
    }

}
