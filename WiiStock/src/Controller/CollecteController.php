<?php

namespace App\Controller;

use App\Entity\Collecte;
use App\Form\CollecteType;
use App\Repository\CollecteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/collecte")
 */
class CollecteController extends AbstractController
{
    /**
     * @Route("/", name="collecte_index", methods={"GET"})
     */
    public function index(CollecteRepository $collecteRepository): Response
    {
        return $this->render('collecte/index.html.twig', [
            'collectes' => $collecteRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="collecte_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $collecte = new Collecte();
        $form = $this->createForm(CollecteType::class, $collecte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date =  new \DateTime('now');
            $collecte->setdate($date);
            $collecte->setNumero("D-" . $date->format('YmdHis'));
            $collecte->setDemandeur($this->getUser());
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($collecte);
            // $entityManager->flush();

            return $this->redirectToRoute('collecte_index');
        }

        return $this->render('collecte/new.html.twig', [
            'collecte' => $collecte,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="collecte_show", methods={"GET"})
     */
    public function show(Collecte $collecte): Response
    {
        return $this->render('collecte/show.html.twig', [
            'collecte' => $collecte,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="collecte_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Collecte $collecte): Response
    {
        $form = $this->createForm(CollecteType::class, $collecte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('collecte_index', [
                'id' => $collecte->getId(),
            ]);
        }

        return $this->render('collecte/edit.html.twig', [
            'collecte' => $collecte,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="collecte_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Collecte $collecte): Response
    {
        if ($this->isCsrfTokenValid('delete'.$collecte->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($collecte);
            $entityManager->flush();
        }

        return $this->redirectToRoute('collecte_index');
    }
}
