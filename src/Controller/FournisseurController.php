<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Form\FournisseurType;
use App\Repository\FournisseurRepository;
use App\Service\UserService;
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
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(FournisseurRepository $fournisseurRepository, UserService $userService)
    {
        $this->fournisseurRepository = $fournisseurRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="fournisseur_api", options={"expose"=true}, methods="POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $refs = $this->fournisseurRepository->findAll();
            $rows = [];
            foreach ($refs as $fournisseur) {
                $fournisseurId = $fournisseur->getId();
                $url['edit'] = $this->generateUrl('fournisseur_edit', ['id' => $fournisseurId]);
                $rows[] = [
                    "Nom" => $fournisseur->getNom(),
                    "Code de référence" => $fournisseur->getCodeReference(),
                    'Actions' => $this->renderView('fournisseur/datatableFournisseurRow.html.twig', [
                        'url' => $url,
                        'fournisseurId' => $fournisseurId
                    ]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="fournisseur_index", methods="GET")
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseur = $this->fournisseurRepository->find($data);
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
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
     * @Route("/supprimer", name="fournisseur_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
         
            $fournisseur = $this->fournisseurRepository->find($data['fournisseur']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($fournisseur);
            $entityManager->flush();
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return new JsonResponse(['results' => null]);
            }

            $search = $request->query->get('term');

            $fournisseur = $this->fournisseurRepository->getIdAndLibelleBySearch($search);

            return new JsonResponse(['results' => $fournisseur]);
        }
        throw new NotFoundHttpException("404");
    }
}
