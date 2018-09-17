<?php

namespace App\Controller;

use App\Entity\Inventaires;
use App\Form\InventairesType;
use App\Repository\InventairesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/inventaires")
 */
class InventairesController extends Controller
{
    /**
     * @Route("/", name="inventaires_index", methods="GET")
     */
    public function index(InventairesRepository $inventairesRepository): Response
    {
        return $this->render('inventaires/index.html.twig', ['inventaires' => $inventairesRepository->findAll()]);
    }

    /**
     * @Route("/affichage/{id}", name="inventaires_affichage", methods="GET")
     */
    public function affichage(Inventaires $inventaire): Response
    {
        return $this->render('inventaires/affichage.html.twig', ['inventaire' => $inventaire]);
    }

    /**
     * @Route("/new", name="inventaires_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $inventaire = new Inventaires();
        $form = $this->createForm(InventairesType::class, $inventaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($inventaire);
            $em->flush();

            return $this->redirectToRoute('inventaires_index');
        }

        return $this->render('inventaires/new.html.twig', [
            'inventaire' => $inventaire,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="inventaires_show", methods="GET")
     */
    public function show(Inventaires $inventaire): Response
    {
        return $this->render('inventaires/show.html.twig', ['inventaire' => $inventaire]);
    }

    /**
     * @Route("/{id}/edit", name="inventaires_edit", methods="GET|POST")
     */
    public function edit(Request $request, Inventaires $inventaire): Response
    {
        $form = $this->createForm(InventairesType::class, $inventaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('inventaires_edit', ['id' => $inventaire->getId()]);
        }

        return $this->render('inventaires/edit.html.twig', [
            'inventaire' => $inventaire,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="inventaires_delete", methods="DELETE")
     */
    public function delete(Request $request, Inventaires $inventaire): Response
    {
        if ($this->isCsrfTokenValid('delete'.$inventaire->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($inventaire);
            $em->flush();
        }

        return $this->redirectToRoute('inventaires_index');
    }
}
