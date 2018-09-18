<?php

namespace App\Controller;

use App\Entity\Marques;
use App\Form\MarquesType;
use App\Repository\MarquesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parc/admin/marques")
 */
class MarquesController extends Controller
{
    /**
     * @Route("/", name="marques_index", methods="GET")
     */
    public function index(MarquesRepository $marquesRepository): Response
    {
        return $this->render('marques/index.html.twig', ['marques' => $marquesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="marques_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $marque = new Marques();
        $form = $this->createForm(MarquesType::class, $marque);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($marque);
            $em->flush();

            return $this->redirectToRoute('marques_index');
        }

        return $this->render('marques/new.html.twig', [
            'marque' => $marque,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="marques_show", methods="GET")
     */
    public function show(Marques $marque): Response
    {
        return $this->render('marques/show.html.twig', [
            'marque' => $marque,
            'parcs' => $marque->getParcs(),    
        ]);
    }

    /**
     * @Route("/{id}/edit", name="marques_edit", methods="GET|POST")
     */
    public function edit(Request $request, Marques $marque): Response
    {
        $form = $this->createForm(MarquesType::class, $marque);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('parc_parametrage');
        }

        return $this->render('marques/edit.html.twig', [
            'marque' => $marque,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="marques_delete", methods="DELETE")
     */
    public function delete(Request $request, Marques $marque): Response
    {
        if ($this->isCsrfTokenValid('delete'.$marque->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($marque);
            $em->flush();
        }

        return $this->redirectToRoute('marques_index');
    }
}
