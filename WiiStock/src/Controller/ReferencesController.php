<?php

namespace App\Controller;

use App\Entity\References;
use App\Form\ReferencesType;
use App\Repository\ReferencesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/references")
 */
class ReferencesController extends Controller
{
    /**
     * @Route("/", name="references_index", methods="GET")
     */
    public function index(ReferencesRepository $referencesRepository): Response
    {
        return $this->render('references/index.html.twig', ['references' => $referencesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="references_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $reference = new References();
        $form = $this->createForm(ReferencesType::class, $reference);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($reference);
            $em->flush();

            return $this->redirectToRoute('references_index');
        }

        return $this->render('references/new.html.twig', [
            'reference' => $reference,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="references_show", methods="GET")
     */
    public function show(References $reference): Response
    {
        return $this->render('references/show.html.twig', ['reference' => $reference]);
    }

    /**
     * @Route("/{id}/edit", name="references_edit", methods="GET|POST")
     */
    public function edit(Request $request, References $reference): Response
    {
        $form = $this->createForm(ReferencesType::class, $reference);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('references_edit', ['id' => $reference->getId()]);
        }

        return $this->render('references/edit.html.twig', [
            'reference' => $reference,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="references_delete", methods="DELETE")
     */
    public function delete(Request $request, References $reference): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reference->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($reference);
            $em->flush();
        }

        return $this->redirectToRoute('references_index');
    }
}
