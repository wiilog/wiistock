<?php

namespace App\Controller;

use App\Entity\Parcs;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ParcsType;

/**
 * @Route("/parc")
 */

class ParcController extends AbstractController
{

    /**
     * @Route("/list")
     */
    public function index()
    {
        return $this->render('parc/index.html.twig', [
            'controller_name' => 'ParcController',
        ]);
    }

    /**
     * @Route("/create", name="parc_create", methods="GET|POST")
     */
    public function create(Request $request) : Response
    {
        $parc = new Parcs();
        $form = $this->createForm(ParcsType::class, $parc);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('validation')->isClicked()) {
                $parc = $form->getData();

                $em->persist($parc);
                $em->flush();
            }

            return $this->redirectToRoute('parc_list');
        }

        return $this->render('parc/create.html.twig', [
            'controller_name' => 'CreateController',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/edit", name="parc_edit", methods="GET|POST")
     */
    public function edit(Request $request, Parcs $parc)
    {
        $form = $this->createForm(ParcsType::class, $parc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('parc_list');
        }

        return $this->render('parc/edit.html.twig', [
            'controller_name' => 'EditController',
            'parc' => $parc,
            'form' => $form->createView(),
        ]);
    }
}
