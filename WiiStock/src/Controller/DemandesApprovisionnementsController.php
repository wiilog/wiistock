<?php

namespace App\Controller;

use App\Entity\DemandesApprovisionnements;
use App\Form\DemandesApprovisionnementsType;
use App\Repository\DemandesApprovisionnementsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/demandes/approvisionnements")
 */
class DemandesApprovisionnementsController extends Controller
{
    /**
     * @Route("/", name="demandes_approvisionnements_index", methods="GET")
     */
    public function index(DemandesApprovisionnementsRepository $demandesApprovisionnementsRepository): Response
    {
        return $this->render('demandes_approvisionnements/index.html.twig', ['demandes_approvisionnements' => $demandesApprovisionnementsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="demandes_approvisionnements_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $demandesApprovisionnement = new DemandesApprovisionnements();
        $form = $this->createForm(DemandesApprovisionnementsType::class, $demandesApprovisionnement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($demandesApprovisionnement);
            $em->flush();

            return $this->redirectToRoute('demandes_approvisionnements_index');
        }

        return $this->render('demandes_approvisionnements/new.html.twig', [
            'demandes_approvisionnement' => $demandesApprovisionnement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_approvisionnements_show", methods="GET")
     */
    public function show(DemandesApprovisionnements $demandesApprovisionnement): Response
    {
        return $this->render('demandes_approvisionnements/show.html.twig', ['demandes_approvisionnement' => $demandesApprovisionnement]);
    }

    /**
     * @Route("/{id}/edit", name="demandes_approvisionnements_edit", methods="GET|POST")
     */
    public function edit(Request $request, DemandesApprovisionnements $demandesApprovisionnement): Response
    {
        $form = $this->createForm(DemandesApprovisionnementsType::class, $demandesApprovisionnement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('demandes_approvisionnements_edit', ['id' => $demandesApprovisionnement->getId()]);
        }

        return $this->render('demandes_approvisionnements/edit.html.twig', [
            'demandes_approvisionnement' => $demandesApprovisionnement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_approvisionnements_delete", methods="DELETE")
     */
    public function delete(Request $request, DemandesApprovisionnements $demandesApprovisionnement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$demandesApprovisionnement->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($demandesApprovisionnement);
            $em->flush();
        }

        return $this->redirectToRoute('demandes_approvisionnements_index');
    }
}
