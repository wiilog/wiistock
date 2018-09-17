<?php

namespace App\Controller;

use App\Entity\DemandesTransferts;
use App\Form\DemandesTransfertsType;
use App\Repository\DemandesTransfertsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/demandes_transferts")
 */
class DemandesTransfertsController extends Controller
{
    /**
     * @Route("/", name="demandes_transferts_index", methods="GET")
     */
    public function index(DemandesTransfertsRepository $demandesTransfertsRepository): Response
    {
        return $this->render('demandes_transferts/index.html.twig', ['demandes_transferts' => $demandesTransfertsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="demandes_transferts_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $demandesTransfert = new DemandesTransferts();
        $form = $this->createForm(DemandesTransfertsType::class, $demandesTransfert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($demandesTransfert);
            $em->flush();

            return $this->redirectToRoute('demandes_transferts_index');
        }

        return $this->render('demandes_transferts/new.html.twig', [
            'demandes_transfert' => $demandesTransfert,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_transferts_show", methods="GET")
     */
    public function show(DemandesTransferts $demandesTransfert): Response
    {
        return $this->render('demandes_transferts/show.html.twig', ['demandes_transfert' => $demandesTransfert]);
    }

    /**
     * @Route("/{id}/edit", name="demandes_transferts_edit", methods="GET|POST")
     */
    public function edit(Request $request, DemandesTransferts $demandesTransfert): Response
    {
        $form = $this->createForm(DemandesTransfertsType::class, $demandesTransfert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('demandes_transferts_edit', ['id' => $demandesTransfert->getId()]);
        }

        return $this->render('demandes_transferts/edit.html.twig', [
            'demandes_transfert' => $demandesTransfert,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_transferts_delete", methods="DELETE")
     */
    public function delete(Request $request, DemandesTransferts $demandesTransfert): Response
    {
        if ($this->isCsrfTokenValid('delete'.$demandesTransfert->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($demandesTransfert);
            $em->flush();
        }

        return $this->redirectToRoute('demandes_transferts_index');
    }
}
