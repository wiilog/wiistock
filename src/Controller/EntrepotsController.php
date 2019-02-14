<?php

namespace App\Controller;

use App\Entity\Entrepots;
use App\Form\EntrepotsType;
use App\Repository\EntrepotsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/entrepots")
 */
class EntrepotsController extends AbstractController
{
    /**
     * @Route("/", name="entrepots_index", methods="GET")
     */
    public function index(EntrepotsRepository $entrepotsRepository) : Response
    {
        return $this->render('entrepots/index.html.twig', ['entrepots' => $entrepotsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="entrepots_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $entrepot = new Entrepots();
        $form = $this->createForm(EntrepotsType::class, $entrepot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entrepot);
            $em->flush();

            return $this->redirectToRoute('entrepots_index');
        }

        return $this->render('entrepots/new.html.twig', [
            'entrepot' => $entrepot,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="entrepots_show", methods="GET")
     */
    public function show(Entrepots $entrepot) : Response
    {
        return $this->render('entrepots/show.html.twig', ['entrepot' => $entrepot]);
    }

    /**
     * @Route("/{id}/edit", name="entrepots_edit", methods="GET|POST")
     */
    public function edit(Request $request, Entrepots $entrepot) : Response
    {
        $form = $this->createForm(EntrepotsType::class, $entrepot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('entrepots_edit', ['id' => $entrepot->getId()]);
        }

        return $this->render('entrepots/edit.html.twig', [
            'entrepot' => $entrepot,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="entrepots_delete", methods="DELETE")
     */
    public function delete(Request $request, Entrepots $entrepot) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $entrepot->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($entrepot);
            $em->flush();
        }

        return $this->redirectToRoute('entrepots_index');
    }

    /**
     * @Route("/add", name="entrepots_add", methods="GET|POST")
     */
    public function add(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $entrepot = new Entrepots();
            $nom = $request->request->get('entrepot');
            $entrepot->setNom($nom);
            $em = $this->getDoctrine()->getManager();
            $em->persist($entrepot);
            $em->flush();
            return new JsonResponse($entrepot->getId());
        }
        throw new NotFoundHttpException('404 not found');
    }
}
