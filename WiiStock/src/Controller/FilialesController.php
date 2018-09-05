<?php

namespace App\Controller;

use App\Entity\Filiales;
use App\Form\FilialesType;
use App\Repository\FilialesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parc/filiales")
 */
class FilialesController extends Controller
{
    /**
     * @Route("/", name="filiales_index", methods="GET")
     */
    public function index(FilialesRepository $filialesRepository): Response
    {
        return $this->render('filiales/index.html.twig', ['filiales' => $filialesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="filiales_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $filiale = new Filiales();
        $form = $this->createForm(FilialesType::class, $filiale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($filiale);
            $em->flush();

            return $this->redirectToRoute('filiales_index');
        }

        return $this->render('filiales/new.html.twig', [
            'filiale' => $filiale,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="filiales_show", methods="GET")
     */
    public function show(Filiales $filiale): Response
    {
        return $this->render('filiales/show.html.twig', ['filiale' => $filiale]);
    }

    /**
     * @Route("/{id}/edit", name="filiales_edit", methods="GET|POST")
     */
    public function edit(Request $request, Filiales $filiale): Response
    {
        $form = $this->createForm(FilialesType::class, $filiale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('filiales_edit', ['id' => $filiale->getId()]);
        }

        return $this->render('filiales/edit.html.twig', [
            'filiale' => $filiale,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="filiales_delete", methods="DELETE")
     */
    public function delete(Request $request, Filiales $filiale): Response
    {
        if ($this->isCsrfTokenValid('delete'.$filiale->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($filiale);
            $em->flush();
        }

        return $this->redirectToRoute('filiales_index');
    }
}
