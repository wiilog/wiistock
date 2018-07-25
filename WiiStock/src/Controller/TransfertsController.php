<?php

namespace App\Controller;

use App\Entity\Transferts;
use App\Form\TransfertsType;
use App\Repository\TransfertsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/transferts")
 */
class TransfertsController extends Controller
{
    /**
     * @Route("/", name="transferts_index", methods="GET")
     */
    public function index(TransfertsRepository $transfertsRepository): Response
    {
        return $this->render('transferts/index.html.twig', ['transferts' => $transfertsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="transferts_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $transfert = new Transferts();
        $form = $this->createForm(TransfertsType::class, $transfert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($transfert);
            $em->flush();

            return $this->redirectToRoute('transferts_index');
        }

        return $this->render('transferts/new.html.twig', [
            'transfert' => $transfert,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="transferts_show", methods="GET")
     */
    public function show(Transferts $transfert): Response
    {
        return $this->render('transferts/show.html.twig', ['transfert' => $transfert]);
    }

    /**
     * @Route("/{id}/edit", name="transferts_edit", methods="GET|POST")
     */
    public function edit(Request $request, Transferts $transfert): Response
    {
        $form = $this->createForm(TransfertsType::class, $transfert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('transferts_edit', ['id' => $transfert->getId()]);
        }

        return $this->render('transferts/edit.html.twig', [
            'transfert' => $transfert,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="transferts_delete", methods="DELETE")
     */
    public function delete(Request $request, Transferts $transfert): Response
    {
        if ($this->isCsrfTokenValid('delete'.$transfert->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($transfert);
            $em->flush();
        }

        return $this->redirectToRoute('transferts_index');
    }
}
