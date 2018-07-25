<?php

namespace App\Controller;

use App\Entity\Preparations;
use App\Form\PreparationsType;
use App\Repository\PreparationsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/preparations")
 */
class PreparationsController extends Controller
{
    /**
     * @Route("/", name="preparations_index", methods="GET")
     */
    public function index(PreparationsRepository $preparationsRepository): Response
    {
        return $this->render('preparations/index.html.twig', ['preparations' => $preparationsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="preparations_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $preparation = new Preparations();
        $form = $this->createForm(PreparationsType::class, $preparation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();

            return $this->redirectToRoute('preparations_index');
        }

        return $this->render('preparations/new.html.twig', [
            'preparation' => $preparation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="preparations_show", methods="GET")
     */
    public function show(Preparations $preparation): Response
    {
        return $this->render('preparations/show.html.twig', ['preparation' => $preparation]);
    }

    /**
     * @Route("/{id}/edit", name="preparations_edit", methods="GET|POST")
     */
    public function edit(Request $request, Preparations $preparation): Response
    {
        $form = $this->createForm(PreparationsType::class, $preparation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('preparations_edit', ['id' => $preparation->getId()]);
        }

        return $this->render('preparations/edit.html.twig', [
            'preparation' => $preparation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="preparations_delete", methods="DELETE")
     */
    public function delete(Request $request, Preparations $preparation): Response
    {
        if ($this->isCsrfTokenValid('delete'.$preparation->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($preparation);
            $em->flush();
        }

        return $this->redirectToRoute('preparations_index');
    }
}
