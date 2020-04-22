<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Repository\ReceptionRepository;
use App\Service\UserService;
use App\Service\FournisseurDataService;
use Doctrine\ORM\EntityManagerInterface;
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
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var FournisseurDataService
     */
    private $fournisseurDataService;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(FournisseurDataService $fournisseurDataService,
                                ReceptionRepository $receptionRepository,
                                UserService $userService) {
        $this->fournisseurDataService = $fournisseurDataService;
        $this->userService = $userService;
        $this->receptionRepository = $receptionRepository;
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
            $data = $this->fournisseurDataService->getFournisseurDataByParams($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="fournisseur_index", methods="GET")
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_FOUR)) {
            return $this->redirectToRoute('access_denied');
        }
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

        return $this->render('fournisseur/index.html.twig', [
            'fournisseur' => $fournisseurRepository->findAll()
        ]);
    }

    /**
     * @Route("/creer", name="fournisseur_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

			// unicité du code fournisseur
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $codeAlreadyUsed = intval($fournisseurRepository->countByCode($data['Code']));

			if ($codeAlreadyUsed) {
				return new JsonResponse([
					'success' => false,
					'msg' => "Ce code fournisseur est déjà utilisé.",
				]);
			}

            $fournisseur = new Fournisseur();
            $fournisseur
				->setNom($data["Nom"])
				->setCodeReference($data["Code"]);
            $entityManager->persist($fournisseur);
            $entityManager->flush();

			return new JsonResponse(['success' => true, 'id' => $fournisseur->getId(), 'text' => $fournisseur->getNom()]);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="fournisseur_api_edit", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

            $fournisseur = $fournisseurRepository->find($data['id']);
            $json = $this->renderView('fournisseur/modalEditFournisseurContent.html.twig', [
                'fournisseur' => $fournisseur,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="fournisseur_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $fournisseur = $fournisseurRepository->find($data['id']);
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
        $articleFournisseurRepository = $this->getDoctrine()->getRepository(ArticleFournisseur::class);
        $receptionReferenceArticleRepository = $this->getDoctrine()->getRepository(ReceptionReferenceArticle::class);
        $arrivageRepository = $this->getDoctrine()->getRepository(Arrivage::class);
        $receptionRepository = $this->getDoctrine()->getRepository(Reception::class);

        $AF = $articleFournisseurRepository->countByFournisseur($fournisseurId);
    	if ($AF > 0) $usedBy[] = 'articles fournisseur';

    	$receptions = $receptionRepository->countByFournisseur($fournisseurId);
    	if ($receptions > 0) $usedBy[] = 'réceptions';

		$ligneReceptions = $receptionReferenceArticleRepository->countByFournisseurId($fournisseurId);
		if ($ligneReceptions > 0) $usedBy[] = 'lignes réception';

		$arrivages = $arrivageRepository->countByFournisseur($fournisseurId);
		if ($arrivages > 0) $usedBy[] = 'arrivages';

        return $usedBy;
    }


    /**
     * @Route("/supprimer", name="fournisseur_delete",  options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $fournisseurId = $data['fournisseur'] ?? null;
            if ($fournisseurId) {
                $fournisseur = $fournisseurRepository->find($fournisseurId);

                // on vérifie que le fournisseur n'est plus utilisé
                $usedFournisseur = $this->isFournisseurUsed($fournisseurId);

                if (!empty($usedFournisseur)) {
                    return new JsonResponse(false);
                }

                $entityManager->remove($fournisseur);
                $entityManager->flush();
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete", name="get_fournisseur", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function getFournisseur(Request $request,
                                   EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $fournisseur = $fournisseurRepository->getIdAndLibelleBySearch($search);

            return new JsonResponse(['results' => $fournisseur]);
        }
        throw new NotFoundHttpException("404");
    }
}
