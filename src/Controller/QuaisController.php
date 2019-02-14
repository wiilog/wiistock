<?php

namespace App\Controller;

use App\Entity\Quais;
use App\Entity\Entrepots;
use App\Form\QuaisType;
use App\Repository\QuaisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/stock/quais")
 */
class QuaisController extends Controller
{
    /**
     * @Route("/remove", name="quais_remove")
     */
    public function remove(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $id = $request->request->get('id');
            $quai = $this->getDoctrine()->getRepository(Quais::class)->find($id);
            $em = $this->getDoctrine()->getManager();
            $em->remove($quai);
            $em->flush();

            return new Response();
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/", name="quais_index", methods="GET")
     */
    public function index(QuaisRepository $quaisRepository) : Response
    {
        return $this->render('quais/index.html.twig', ['quais' => $quaisRepository->findAll()]);
    }

    /**
     * @Route("/new", name="quais_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $quai = new Quais();
        $form = $this->createForm(QuaisType::class, $quai);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($quai);
            $em->flush();

            return $this->redirectToRoute('quais_index');
        }

        return $this->render('quais/new.html.twig', [
            'quai' => $quai,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="quais_show", methods="GET")
     */
    public function show(Quais $quai) : Response
    {
        return $this->render('quais/show.html.twig', ['quai' => $quai]);
    }

    /**
     * @Route("/{id}/edit", name="quais_edit", methods="GET|POST")
     */
    public function edit(Request $request, Quais $quai) : Response
    {
        $form = $this->createForm(QuaisType::class, $quai);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('quais_edit', ['id' => $quai->getId()]);
        }

        return $this->render('quais/edit.html.twig', [
            'quai' => $quai,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="quais_delete", methods="DELETE")
     */
    public function delete(Request $request, Quais $quai) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $quai->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($quai);
            $em->flush();
        }

        return $this->redirectToRoute('quais_index');
    }

    /**
     * @Route("/add", name="quais_add", methods="GET|POST")
     */
    public function add(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $quai = new Quais();
            $nom = $request->request->get('quai');
            $id = $request->request->get('id');
            $quai->setNom($nom);
            $quai->setEntrepots($this->getDoctrine()->getRepository(Entrepots::class)->find($id));
            $em = $this->getDoctrine()->getManager();
            $em->persist($quai);
            $em->flush();
            return new JsonResponse($quai->getId());
        }
        throw new NotFoundHttpException('404 not found');
    }
}
