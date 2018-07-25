<?php

namespace App\Controller;

use App\Entity\Historiques;
use App\Form\HistoriquesType;
use App\Repository\HistoriquesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/historiques")
 */
class HistoriquesController extends Controller
{
    /**
     * @Route("/", name="historiques_index", methods="GET")
     */
    public function index(HistoriquesRepository $historiquesRepository): Response
    {
        return $this->render('historiques/index.html.twig', ['historiques' => $historiquesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="historiques_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $historique = new Historiques();
        $form = $this->createForm(HistoriquesType::class, $historique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($historique);
            $em->flush();

            return $this->redirectToRoute('historiques_index');
        }

        return $this->render('historiques/new.html.twig', [
            'historique' => $historique,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="historiques_show", methods="GET")
     */
    public function show(Historiques $historique): Response
    {
        return $this->render('historiques/show.html.twig', ['historique' => $historique]);
    }

    /**
     * @Route("/{id}/edit", name="historiques_edit", methods="GET|POST")
     */
    public function edit(Request $request, Historiques $historique): Response
    {
        $form = $this->createForm(HistoriquesType::class, $historique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('historiques_edit', ['id' => $historique->getId()]);
        }

        return $this->render('historiques/edit.html.twig', [
            'historique' => $historique,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="historiques_delete", methods="DELETE")
     */
    public function delete(Request $request, Historiques $historique): Response
    {
        if ($this->isCsrfTokenValid('delete'.$historique->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($historique);
            $em->flush();
        }

        return $this->redirectToRoute('historiques_index');
    }
}
