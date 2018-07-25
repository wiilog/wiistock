<?php

namespace App\Controller;

use App\Entity\Groupes;
use App\Form\GroupesType;
use App\Repository\GroupesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/groupes")
 */
class GroupesController extends Controller
{
    /**
     * @Route("/", name="groupes_index", methods="GET")
     */
    public function index(GroupesRepository $groupesRepository): Response
    {
        return $this->render('groupes/index.html.twig', ['groupes' => $groupesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="groupes_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $groupe = new Groupes();
        $form = $this->createForm(GroupesType::class, $groupe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($groupe);
            $em->flush();

            return $this->redirectToRoute('groupes_index');
        }

        return $this->render('groupes/new.html.twig', [
            'groupe' => $groupe,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="groupes_show", methods="GET")
     */
    public function show(Groupes $groupe): Response
    {
        return $this->render('groupes/show.html.twig', ['groupe' => $groupe]);
    }

    /**
     * @Route("/{id}/edit", name="groupes_edit", methods="GET|POST")
     */
    public function edit(Request $request, Groupes $groupe): Response
    {
        $form = $this->createForm(GroupesType::class, $groupe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('groupes_edit', ['id' => $groupe->getId()]);
        }

        return $this->render('groupes/edit.html.twig', [
            'groupe' => $groupe,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="groupes_delete", methods="DELETE")
     */
    public function delete(Request $request, Groupes $groupe): Response
    {
        if ($this->isCsrfTokenValid('delete'.$groupe->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($groupe);
            $em->flush();
        }

        return $this->redirectToRoute('groupes_index');
    }
}
