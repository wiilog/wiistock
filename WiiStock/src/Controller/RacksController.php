<?php

namespace App\Controller;

use App\Entity\Racks;
use App\Form\RacksType;
use App\Repository\RacksRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/racks")
 */
class RacksController extends Controller
{
    /**
     * @Route("/", name="racks_index", methods="GET")
     */
    public function index(RacksRepository $racksRepository): Response
    {
        return $this->render('racks/index.html.twig', ['racks' => $racksRepository->findAll()]);
    }

    /**
     * @Route("/new", name="racks_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $rack = new Racks();
        $form = $this->createForm(RacksType::class, $rack);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($rack);
            $em->flush();

            return $this->redirectToRoute('racks_index');
        }

        return $this->render('racks/new.html.twig', [
            'rack' => $rack,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="racks_show", methods="GET")
     */
    public function show(Racks $rack): Response
    {
        return $this->render('racks/show.html.twig', ['rack' => $rack]);
    }

    /**
     * @Route("/{id}/edit", name="racks_edit", methods="GET|POST")
     */
    public function edit(Request $request, Racks $rack): Response
    {
        $form = $this->createForm(RacksType::class, $rack);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('racks_edit', ['id' => $rack->getId()]);
        }

        return $this->render('racks/edit.html.twig', [
            'rack' => $rack,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="racks_delete", methods="DELETE")
     */
    public function delete(Request $request, Racks $rack): Response
    {
        if ($this->isCsrfTokenValid('delete'.$rack->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($rack);
            $em->flush();
        }

        return $this->redirectToRoute('racks_index');
    }
}
