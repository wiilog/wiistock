<?php

namespace App\Controller;

use App\Entity\Zones;
use App\Form\ZonesType;
use App\Repository\ZonesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/zones")
 */
class ZonesController extends Controller
{
    /**
     * @Route("/", name="zones_index", methods="GET")
     */
    public function index(ZonesRepository $zonesRepository): Response
    {
        return $this->render('zones/index.html.twig', ['zones' => $zonesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="zones_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $zone = new Zones();
        $form = $this->createForm(ZonesType::class, $zone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($zone);
            $em->flush();

            return $this->redirectToRoute('zones_index');
        }

        return $this->render('zones/new.html.twig', [
            'zone' => $zone,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="zones_show", methods="GET")
     */
    public function show(Zones $zone): Response
    {
        return $this->render('zones/show.html.twig', ['zone' => $zone]);
    }

    /**
     * @Route("/{id}/edit", name="zones_edit", methods="GET|POST")
     */
    public function edit(Request $request, Zones $zone): Response
    {
        $form = $this->createForm(ZonesType::class, $zone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('zones_edit', ['id' => $zone->getId()]);
        }

        return $this->render('zones/edit.html.twig', [
            'zone' => $zone,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="zones_delete", methods="DELETE")
     */
    public function delete(Request $request, Zones $zone): Response
    {
        if ($this->isCsrfTokenValid('delete'.$zone->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($zone);
            $em->flush();
        }

        return $this->redirectToRoute('zones_index');
    }
}
