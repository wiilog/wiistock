<?php

namespace App\Controller;

use App\Entity\ReferencesFournisseurs;
use App\Form\ReferencesFournisseursType;
use App\Repository\ReferencesFournisseursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/references/fournisseurs")
 */
class ReferencesFournisseursController extends Controller
{
    /**
     * @Route("/", name="references_fournisseurs_index", methods="GET")
     */
    public function index(ReferencesFournisseursRepository $referencesFournisseursRepository): Response
    {
        return $this->render('references_fournisseurs/index.html.twig', ['references_fournisseurs' => $referencesFournisseursRepository->findAll()]);
    }

    /**
     * @Route("/new", name="references_fournisseurs_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $referencesFournisseur = new ReferencesFournisseurs();
        $form = $this->createForm(ReferencesFournisseursType::class, $referencesFournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($referencesFournisseur);
            $em->flush();

            return $this->redirectToRoute('references_fournisseurs_index');
        }

        return $this->render('references_fournisseurs/new.html.twig', [
            'references_fournisseur' => $referencesFournisseur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="references_fournisseurs_show", methods="GET")
     */
    public function show(ReferencesFournisseurs $referencesFournisseur): Response
    {
        return $this->render('references_fournisseurs/show.html.twig', ['references_fournisseur' => $referencesFournisseur]);
    }

    /**
     * @Route("/{id}/edit", name="references_fournisseurs_edit", methods="GET|POST")
     */
    public function edit(Request $request, ReferencesFournisseurs $referencesFournisseur): Response
    {
        $form = $this->createForm(ReferencesFournisseursType::class, $referencesFournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('references_fournisseurs_edit', ['id' => $referencesFournisseur->getId()]);
        }

        return $this->render('references_fournisseurs/edit.html.twig', [
            'references_fournisseur' => $referencesFournisseur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="references_fournisseurs_delete", methods="DELETE")
     */
    public function delete(Request $request, ReferencesFournisseurs $referencesFournisseur): Response
    {
        if ($this->isCsrfTokenValid('delete'.$referencesFournisseur->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($referencesFournisseur);
            $em->flush();
        }

        return $this->redirectToRoute('references_fournisseurs_index');
    }
}
