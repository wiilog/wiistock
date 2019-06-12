<?php

namespace App\Controller;

use App\Entity\Transporteur;
use App\Form\TransporteurType;
use App\Repository\TransporteurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/transporteur")
 */
class TransporteurController extends AbstractController
{
    /**
     * @Route("/", name="transporteur_index", methods={"GET"})
     */
    public function index(TransporteurRepository $transporteurRepository): Response
    {
        return $this->render('transporteur/index.html.twig', [
            'transporteurs' => $transporteurRepository->findAll(),
        ]);
    }


    /**
     * @Route("/new", name="transporteur_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $transporteur = new Transporteur();
        $form = $this->createForm(TransporteurType::class, $transporteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($transporteur);
            $entityManager->flush();

            return $this->redirectToRoute('transporteur_index');
        }

        return $this->render('transporteur/new.html.twig', [
            'transporteur' => $transporteur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="transporteur_show", methods={"GET"})
     */
    public function show(Transporteur $transporteur): Response
    {
        return $this->render('transporteur/show.html.twig', [
            'transporteur' => $transporteur,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="transporteur_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Transporteur $transporteur): Response
    {
        $form = $this->createForm(TransporteurType::class, $transporteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('transporteur_index', [
                'id' => $transporteur->getId(),
            ]);
        }

        return $this->render('transporteur/edit.html.twig', [
            'transporteur' => $transporteur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="transporteur_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Transporteur $transporteur): Response
    {
        if ($this->isCsrfTokenValid('delete'.$transporteur->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($transporteur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('transporteur_index');
    }
}
