<?php

namespace App\Controller;

use App\Entity\DemandesLivraisons;
use App\Form\DemandesLivraisonsType;
use App\Repository\DemandesLivraisonsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/demandes/livraisons")
 */
class DemandesLivraisonsController extends Controller
{
    /**
     * @Route("/", name="demandes_livraisons_index", methods="GET")
     */
    public function index(DemandesLivraisonsRepository $demandesLivraisonsRepository): Response
    {
        return $this->render('demandes_livraisons/index.html.twig', ['demandes_livraisons' => $demandesLivraisonsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="demandes_livraisons_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $demandesLivraison = new DemandesLivraisons();
        $form = $this->createForm(DemandesLivraisonsType::class, $demandesLivraison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($demandesLivraison);
            $em->flush();

            return $this->redirectToRoute('demandes_livraisons_index');
        }

        return $this->render('demandes_livraisons/new.html.twig', [
            'demandes_livraison' => $demandesLivraison,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_livraisons_show", methods="GET")
     */
    public function show(DemandesLivraisons $demandesLivraison): Response
    {
        return $this->render('demandes_livraisons/show.html.twig', ['demandes_livraison' => $demandesLivraison]);
    }

    /**
     * @Route("/{id}/edit", name="demandes_livraisons_edit", methods="GET|POST")
     */
    public function edit(Request $request, DemandesLivraisons $demandesLivraison): Response
    {
        $form = $this->createForm(DemandesLivraisonsType::class, $demandesLivraison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('demandes_livraisons_edit', ['id' => $demandesLivraison->getId()]);
        }

        return $this->render('demandes_livraisons/edit.html.twig', [
            'demandes_livraison' => $demandesLivraison,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_livraisons_delete", methods="DELETE")
     */
    public function delete(Request $request, DemandesLivraisons $demandesLivraison): Response
    {
        if ($this->isCsrfTokenValid('delete'.$demandesLivraison->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($demandesLivraison);
            $em->flush();
        }

        return $this->redirectToRoute('demandes_livraisons_index');
    }
}
