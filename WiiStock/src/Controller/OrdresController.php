<?php

namespace App\Controller;

use App\Entity\Ordres;
use App\Form\OrdresType;
use App\Repository\OrdresRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/ordres")
 */
class OrdresController extends Controller
{
    /**
     * @Route("/", name="ordres_index", methods="GET")
     */
    public function index(OrdresRepository $ordresRepository): Response
    {
        return $this->render('ordres/index.html.twig', ['ordres' => $ordresRepository->findAll()]);
    }

    /**
     * @Route("/new", name="ordres_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $ordre = new Ordres();
        $form = $this->createForm(OrdresType::class, $ordre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($ordre);
            $em->flush();

            return $this->redirectToRoute('ordres_index');
        }

        return $this->render('ordres/new.html.twig', [
            'ordre' => $ordre,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="ordres_show", methods="GET")
     */
    public function show(Ordres $ordre): Response
    {
        return $this->render('ordres/show.html.twig', ['ordre' => $ordre]);
    }

    /**
     * @Route("/{id}/edit", name="ordres_edit", methods="GET|POST")
     */
    public function edit(Request $request, Ordres $ordre): Response
    {
        $form = $this->createForm(OrdresType::class, $ordre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('ordres_edit', ['id' => $ordre->getId()]);
        }

        return $this->render('ordres/edit.html.twig', [
            'ordre' => $ordre,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="ordres_delete", methods="DELETE")
     */
    public function delete(Request $request, Ordres $ordre): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ordre->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($ordre);
            $em->flush();
        }

        return $this->redirectToRoute('ordres_index');
    }
}
