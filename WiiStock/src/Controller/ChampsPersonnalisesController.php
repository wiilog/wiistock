<?php

namespace App\Controller;

use App\Entity\ChampsPersonnalises;
use App\Form\ChampsPersonnalisesType;
use App\Repository\ChampsPersonnalisesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/super/admin/champs_personnalises")
 */
class ChampsPersonnalisesController extends Controller
{
    /**
     * @Route("/", name="champs_personnalises_index", methods="GET")
     */
    public function index(ChampsPersonnalisesRepository $champsPersonnalisesRepository): Response
    {
        return $this->render('champs_personnalises/index.html.twig', ['champs_personnalises' => $champsPersonnalisesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="champs_personnalises_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $champsPersonnalise = new ChampsPersonnalises();
        $form = $this->createForm(ChampsPersonnalisesType::class, $champsPersonnalise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($champsPersonnalise);
            $em->flush();

            return $this->redirectToRoute('champs_personnalises_index');
        }

        return $this->render('champs_personnalises/new.html.twig', [
            'champs_personnalise' => $champsPersonnalise,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="champs_personnalises_show", methods="GET")
     */
    public function show(ChampsPersonnalises $champsPersonnalise): Response
    {
        return $this->render('champs_personnalises/show.html.twig', ['champs_personnalise' => $champsPersonnalise]);
    }

    /**
     * @Route("/{id}/edit", name="champs_personnalises_edit", methods="GET|POST")
     */
    public function edit(Request $request, ChampsPersonnalises $champsPersonnalise): Response
    {
        $form = $this->createForm(ChampsPersonnalisesType::class, $champsPersonnalise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('champs_personnalises_edit', ['id' => $champsPersonnalise->getId()]);
        }

        return $this->render('champs_personnalises/edit.html.twig', [
            'champs_personnalise' => $champsPersonnalise,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="champs_personnalises_delete", methods="DELETE")
     */
    public function delete(Request $request, ChampsPersonnalises $champsPersonnalise): Response
    {
        if ($this->isCsrfTokenValid('delete'.$champsPersonnalise->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($champsPersonnalise);
            $em->flush();
        }

        return $this->redirectToRoute('champs_personnalises_index');
    }
}
