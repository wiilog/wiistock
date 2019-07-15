<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Fournisseur;
use App\Entity\Menu;
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
use Knp\Component\Pager\PaginatorInterface;

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
     * @var UserService
     */
    private $userService;


    public function __construct(FournisseurDataService $fournisseurDataService,ReceptionReferenceArticleRepository $receptionReferenceArticleRepository, ReceptionRepository $receptionRepository, ArticleFournisseurRepository $articleFournisseurRepository, FournisseurRepository $fournisseurRepository, UserService $userService)
    {
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
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::LIST)) {
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
        if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('fournisseur/index.html.twig', ['fournisseur' => $this->fournisseurRepository->findAll()]);
    }

    /**
     * @Route("/creer", name="fournisseur_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $fournisseur = new Fournisseur();
            $fournisseur->setNom($data["Nom"]);
            $fournisseur->setCodeReference($data["Code"]);
            $em->persist($fournisseur);
            $em->flush();
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="fournisseur_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE_EDIT)) {
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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseur = $this->fournisseurRepository->find($data['id']);
            $fournisseur
                ->setNom($data['nom'])
                ->setCodeReference($data['CodeReference']);
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
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($this->countUsedFournisseurs($fournisseurId) == 0) {
                $delete = true;
                $html = $this->renderView('fournisseur/modalDeleteFournisseurRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('fournisseur/modalDeleteFournisseurWrong.html.twig', ['delete' => false]);
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    private function countUsedFournisseurs($fournisseurId)
    {
        $usedFournisseur = $this->articleFournisseurRepository->countByFournisseur($fournisseurId);
        $usedFournisseur += $this->receptionRepository->countByFournisseur($fournisseurId);
        $usedFournisseur += $this->receptionReferenceArticleRepository->countByFournisseur($fournisseurId);
        // $$usedFournisseur += $this->mouvementRepository->countByFournisseur($fournisseurId);

        return $usedFournisseur;
    }


    /**
     * @Route("/supprimer", name="fournisseur_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            if ($fournisseurId = (int)$data['fournisseur']) {

                $fournisseur = $this->fournisseurRepository->find($fournisseurId);

                // on vérifie que le fournisseur n'est plus utilisé
                $usedFournisseur = $this->countUsedFournisseurs($fournisseurId);

                if ($usedFournisseur > 0) {
                    return new JsonResponse(false);
                }

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($fournisseur);
                $entityManager->flush();
                return new JsonResponse();
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
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::LIST)) {
                return new JsonResponse(['results' => null]);
            }

            $search = $request->query->get('term');

            $fournisseur = $this->fournisseurRepository->getIdAndLibelleBySearch($search);

            return new JsonResponse(['results' => $fournisseur]);
        }
        throw new NotFoundHttpException("404");
    }
}
