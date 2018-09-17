<?php

namespace App\Controller;

use App\Entity\Alertes;
use App\Form\AlertesType;
use App\Repository\AlertesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/admin/alertes")
 */
class AlertesController extends Controller
{
    /**
     * @Route("/", name="alertes_index", methods="GET")
     */
    public function index(AlertesRepository $alertesRepository): Response
    {
        return $this->render('alertes/index.html.twig', ['alertes' => $alertesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="alertes_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $alerte = new Alertes();
        $form = $this->createForm(AlertesType::class, $alerte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($alerte);
            $em->flush();

            return $this->redirectToRoute('alertes_index');
        }

        return $this->render('alertes/new.html.twig', [
            'alerte' => $alerte,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/creation", name="alertes_creation")
     */
    public function creation()
    {
        return $this->render('alertes/creation.html.twig', [
            'controller_name' => 'AlertesController',
        ]);
    }

    /**
     * @Route("/{id}", name="alertes_show", methods="GET")
     */
    public function show(Alertes $alerte): Response
    {
        return $this->render('alertes/show.html.twig', ['alerte' => $alerte]);
    }

    /**
     * @Route("/{id}/edit", name="alertes_edit", methods="GET|POST")
     */
    public function edit(Request $request, Alertes $alerte): Response
    {
        $form = $this->createForm(AlertesType::class, $alerte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('alertes_edit', ['id' => $alerte->getId()]);
        }

        return $this->render('alertes/edit.html.twig', [
            'alerte' => $alerte,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="alertes_delete", methods="DELETE")
     */
    public function delete(Request $request, Alertes $alerte): Response
    {
        if ($this->isCsrfTokenValid('delete'.$alerte->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($alerte);
            $em->flush();
        }

        return $this->redirectToRoute('alertes_index');
    }
}
