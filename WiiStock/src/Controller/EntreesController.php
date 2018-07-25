<?php

namespace App\Controller;

use App\Entity\Entrees;
use App\Form\EntreesType;
use App\Repository\EntreesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/entrees")
 */
class EntreesController extends Controller
{
    /**
     * @Route("/", name="entrees_index", methods="GET")
     */
    public function index(EntreesRepository $entreesRepository): Response
    {
        return $this->render('entrees/index.html.twig', ['entrees' => $entreesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="entrees_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $entree = new Entrees();
        $form = $this->createForm(EntreesType::class, $entree);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entree);
            $em->flush();

            return $this->redirectToRoute('entrees_index');
        }

        return $this->render('entrees/new.html.twig', [
            'entree' => $entree,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="entrees_show", methods="GET")
     */
    public function show(Entrees $entree): Response
    {
        return $this->render('entrees/show.html.twig', ['entree' => $entree]);
    }

    /**
     * @Route("/{id}/edit", name="entrees_edit", methods="GET|POST")
     */
    public function edit(Request $request, Entrees $entree): Response
    {
        $form = $this->createForm(EntreesType::class, $entree);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('entrees_edit', ['id' => $entree->getId()]);
        }

        return $this->render('entrees/edit.html.twig', [
            'entree' => $entree,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="entrees_delete", methods="DELETE")
     */
    public function delete(Request $request, Entrees $entree): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entree->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($entree);
            $em->flush();
        }

        return $this->redirectToRoute('entrees_index');
    }
}
