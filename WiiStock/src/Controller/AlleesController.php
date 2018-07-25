<?php

namespace App\Controller;

use App\Entity\Allees;
use App\Form\AlleesType;
use App\Repository\AlleesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/allees")
 */
class AlleesController extends Controller
{
    /**
     * @Route("/", name="allees_index", methods="GET")
     */
    public function index(AlleesRepository $alleesRepository): Response
    {
        return $this->render('allees/index.html.twig', ['allees' => $alleesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="allees_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $allee = new Allees();
        $form = $this->createForm(AlleesType::class, $allee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($allee);
            $em->flush();

            return $this->redirectToRoute('allees_index');
        }

        return $this->render('allees/new.html.twig', [
            'allee' => $allee,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="allees_show", methods="GET")
     */
    public function show(Allees $allee): Response
    {
        return $this->render('allees/show.html.twig', ['allee' => $allee]);
    }

    /**
     * @Route("/{id}/edit", name="allees_edit", methods="GET|POST")
     */
    public function edit(Request $request, Allees $allee): Response
    {
        $form = $this->createForm(AlleesType::class, $allee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('allees_edit', ['id' => $allee->getId()]);
        }

        return $this->render('allees/edit.html.twig', [
            'allee' => $allee,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="allees_delete", methods="DELETE")
     */
    public function delete(Request $request, Allees $allee): Response
    {
        if ($this->isCsrfTokenValid('delete'.$allee->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($allee);
            $em->flush();
        }

        return $this->redirectToRoute('allees_index');
    }
}
