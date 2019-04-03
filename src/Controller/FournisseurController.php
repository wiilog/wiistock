<?php

namespace App\Controller;

use App\Entity\Fournisseur;
use App\Form\FournisseurType;
use App\Repository\FournisseurRepository;
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
   
    public function __construct(FournisseurRepository $fournisseurRepository)
    {
        $this->fournisseurRepository = $fournisseurRepository;
    }

    /**
     * @Route("/api", name="fournisseur_api", options={"expose"=true}, methods="POST")
     */
    public function api(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
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
                        'fournisseurId'=>$fournisseurId
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
    public function index() : Response
    {
        return $this->render('fournisseur/index.html.twig', ['fournisseur' => $this->fournisseurRepository->findAll()]);
    }

    /**
     * @Route("/creer", name="fournisseur_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $em = $this->getDoctrine()->getEntityManager();

        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
    public function delete(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $fournisseur= $this->fournisseurRepository->find($data['fournisseur']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($fournisseur);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}