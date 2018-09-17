<?php

namespace App\Controller;

use App\Entity\Travees;
use App\Form\TraveesType;
use App\Repository\TraveesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/travees")
 */
class TraveesController extends Controller
{
    /**
     * @Route("/", name="travees_index", methods="GET")
     */
    public function index(TraveesRepository $traveesRepository): Response
    {
        return $this->render('travees/index.html.twig', ['travees' => $traveesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="travees_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $travee = new Travees();
        $form = $this->createForm(TraveesType::class, $travee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($travee);
            $em->flush();

            return $this->redirectToRoute('travees_index');
        }

        return $this->render('travees/new.html.twig', [
            'travee' => $travee,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="travees_show", methods="GET")
     */
    public function show(Travees $travee): Response
    {
        return $this->render('travees/show.html.twig', ['travee' => $travee]);
    }

    /**
     * @Route("/{id}/edit", name="travees_edit", methods="GET|POST")
     */
    public function edit(Request $request, Travees $travee): Response
    {
        $form = $this->createForm(TraveesType::class, $travee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('travees_edit', ['id' => $travee->getId()]);
        }

        return $this->render('travees/edit.html.twig', [
            'travee' => $travee,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="travees_delete", methods="DELETE")
     */
    public function delete(Request $request, Travees $travee): Response
    {
        if ($this->isCsrfTokenValid('delete'.$travee->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($travee);
            $em->flush();
        }

        return $this->redirectToRoute('travees_index');
    }
}
