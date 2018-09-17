<?php

namespace App\Controller;

use App\Entity\Fournisseurs;
use App\Form\FournisseursType;
use App\Repository\FournisseursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/fournisseurs")
 */
class FournisseursController extends Controller
{
    /**
     * @Route("/", name="fournisseurs_index", methods="GET")
     */
    public function index(FournisseursRepository $fournisseursRepository): Response
    {
        return $this->render('fournisseurs/index.html.twig', ['fournisseurs' => $fournisseursRepository->findAll()]);
    }

    /**
     * @Route("/new", name="fournisseurs_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $fournisseur = new Fournisseurs();
        $form = $this->createForm(FournisseursType::class, $fournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($fournisseur);
            $em->flush();

            return $this->redirectToRoute('fournisseurs_index');
        }

        return $this->render('fournisseurs/new.html.twig', [
            'fournisseur' => $fournisseur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="fournisseurs_show", methods="GET")
     */
    public function show(Fournisseurs $fournisseur): Response
    {
        return $this->render('fournisseurs/show.html.twig', ['fournisseur' => $fournisseur]);
    }

    /**
     * @Route("/{id}/edit", name="fournisseurs_edit", methods="GET|POST")
     */
    public function edit(Request $request, Fournisseurs $fournisseur): Response
    {
        $form = $this->createForm(FournisseursType::class, $fournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('fournisseurs_edit', ['id' => $fournisseur->getId()]);
        }

        return $this->render('fournisseurs/edit.html.twig', [
            'fournisseur' => $fournisseur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="fournisseurs_delete", methods="DELETE")
     */
    public function delete(Request $request, Fournisseurs $fournisseur): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fournisseur->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($fournisseur);
            $em->flush();
        }

        return $this->redirectToRoute('fournisseurs_index');
    }
}
