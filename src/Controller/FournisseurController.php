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
     * @Route("/get", name="fournisseur_get", methods="GET")
     */
    public function getReferenceArticles(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $q = $request->query->get('q');
            $refs = $this->fournisseurRepository->findBySearch($q);
            $rows = array();
            foreach ($refs as $ref) {
                $row = [
                    "id" => $ref->getId(),
                    "nom" => $ref->getNom(),
                    "code_reference" => $ref->getCodeReference(),
                ];
                array_push($rows, $row);
            }

            $data = array(
                "total_count" => count($rows),
                "items" => $rows,
            );
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/api", name="fournisseur_api", methods="GET")
     */
    public function fournisseurApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $refs = $this->fournisseurRepository->findAll();
            $rows = [];
            foreach ($refs as $fournisseur) {
                $fournisseurId = $fournisseur->getId();
                $url['edit'] = $this->generateUrl('fournisseur_edit', ['id' => $fournisseurId]);
                $url['show'] = $this->generateUrl('fournisseur_show', ['id' => $fournisseurId]);
                $rows[] = [
                    "Nom" => $fournisseur->getNom(),
                    "Code de réference" => $fournisseur->getCodeReference(),
                    'Actions' => $this->renderView('fournisseur/datatableFournisseurRow.html.twig', ['url' => $url]),
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
    public function index(Request $request) : Response
    {
        return $this->render('fournisseur/index.html.twig', ['fournisseur' => $this->fournisseurRepository->findAll()]);
    }

    /**
     * @Route("/creation/fournisseur", name="creation_fournisseur", methods="GET|POST")
     */
    public function creationFournisseur(Request $request) : Response
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
     * @Route("/{id}", name="fournisseur_show", methods="GET")
     */
    public function show(Fournisseur $fournisseur) : Response
    {
        return $this->render('fournisseur/show.html.twig', ['fournisseur' => $fournisseur]);
    }

    /**
     * @Route("/{id}/edit", name="fournisseur_edit", methods="GET|POST")
     */
    public function edit(Request $request, Fournisseur $fournisseur) : Response
    {
        $form = $this->createForm(FournisseurType::class, $fournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('fournisseur_index');
        }

        return $this->render('fournisseur/edit.html.twig', [
            'fournisseur' => $fournisseur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="fournisseur_delete", methods="DELETE")
     */
    public function delete(Request $request, Fournisseur $fournisseur) : Response
    {
        $receptions = $fournisseur->getreceptions();
        if (count($receptions) === 0) {
            if ($this->isCsrfTokenValid('delete' . $fournisseur->getId(), $request->request->get('_token'))) {
                $em = $this->getDoctrine()->getManager();
                $em->remove($fournisseur);
                $em->flush();
                return $this->redirectToRoute('fournisseur_index');
            }
        } else {
            return $this->redirect($_SERVER['HTTP_REFERER']);
        }
    }
}

// , array(
//     'message' => 'impossible de supprimer un fournisseur utilisé'
// )