<?php

namespace App\Controller;

use App\Entity\Demandes;
use App\Form\DemandesType;
use App\Repository\DemandesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/demandes")
 */
class DemandesController extends Controller
{
    /**
     * @Route("/", name="demandes_index", methods="GET")
     */
    public function index(DemandesRepository $demandesRepository): Response
    {
        return $this->render('demandes/index.html.twig', ['demandes' => $demandesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="demandes_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $demande = new Demandes();
        $form = $this->createForm(DemandesType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($demande);
            $em->flush();

            return $this->redirectToRoute('demandes_index');
        }

        return $this->render('demandes/new.html.twig', [
            'demande' => $demande,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_show", methods="GET")
     */
    public function show(Demandes $demande): Response
    {
        return $this->render('demandes/show.html.twig', ['demande' => $demande]);
    }

    /**
     * @Route("/{id}/edit", name="demandes_edit", methods="GET|POST")
     */
    public function edit(Request $request, Demandes $demande): Response
    {
        $form = $this->createForm(DemandesType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('demandes_edit', ['id' => $demande->getId()]);
        }

        return $this->render('demandes/edit.html.twig', [
            'demande' => $demande,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_delete", methods="DELETE")
     */
    public function delete(Request $request, Demandes $demande): Response
    {
        if ($this->isCsrfTokenValid('delete'.$demande->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($demande);
            $em->flush();
        }

        return $this->redirectToRoute('demandes_index');
    }
}
