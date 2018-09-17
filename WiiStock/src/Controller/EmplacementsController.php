<?php

namespace App\Controller;

use App\Entity\Emplacements;
use App\Form\EmplacementsType;
use App\Repository\EmplacementsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/emplacements")
 */
class EmplacementsController extends Controller
{
    /**
     * @Route("/", name="emplacements_index", methods="GET")
     */
    public function index(EmplacementsRepository $emplacementsRepository): Response
    {
        return $this->render('emplacements/index.html.twig', ['emplacements' => $emplacementsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="emplacements_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $emplacement = new Emplacements();
        $form = $this->createForm(EmplacementsType::class, $emplacement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($emplacement);
            $em->flush();

            return $this->redirectToRoute('emplacements_index');
        }

        return $this->render('emplacements/new.html.twig', [
            'emplacement' => $emplacement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="emplacements_show", methods="GET")
     */
    public function show(Emplacements $emplacement): Response
    {
        return $this->render('emplacements/show.html.twig', ['emplacement' => $emplacement]);
    }

    /**
     * @Route("/{id}/edit", name="emplacements_edit", methods="GET|POST")
     */
    public function edit(Request $request, Emplacements $emplacement): Response
    {
        $form = $this->createForm(EmplacementsType::class, $emplacement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('emplacements_edit', ['id' => $emplacement->getId()]);
        }

        return $this->render('emplacements/edit.html.twig', [
            'emplacement' => $emplacement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="emplacements_delete", methods="DELETE")
     */
    public function delete(Request $request, Emplacements $emplacement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$emplacement->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($emplacement);
            $em->flush();
        }

        return $this->redirectToRoute('emplacements_index');
    }
}
