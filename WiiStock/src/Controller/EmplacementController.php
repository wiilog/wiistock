<?php

namespace App\Controller;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\StatutsRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/emplacement")
 */
class EmplacementController extends AbstractController
{

    /**
     * @Route("/nouvelEmplacement", name="createEmplacement")
     */
    public function createEmplacement(Request $request, StatutsRepository $statutsRepository): Response
    {
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            if(count($data) >= 2)
            {
                $emplacement = new Emplacement();
                $em = $this->getDoctrine()->getManager();

                $emplacement->setNom($data[0]['nom']);
                $statut = $statutsRepository->findById($data[1]['statut']);
                $emplacement->setStatut($statut[0]);

                $em->persist($emplacement);
                $em->flush();

                $data = json_encode($data);
                return new JsonResponse($data);
            }
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="emplacement_index", methods="GET")
     */
    public function index(EmplacementRepository $emplacementRepository, StatutsRepository $statutsRepository): Response
    {
        return $this->render('emplacement/index.html.twig', ['emplacements' => $emplacementRepository->findAll(), 'statuts' => $statutsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="emplacement_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $emplacement = new Emplacement();
        $form = $this->createForm(EmplacementType::class, $emplacement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($emplacement);
            $em->flush();

            return $this->redirectToRoute('emplacement_index');
        }

        return $this->render('emplacement/new.html.twig', [
            'emplacement' => $emplacement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="emplacement_show", methods="GET")
     */
    public function show(Emplacement $emplacement, StatutsRepository $statutsRepository): Response
    {
        return $this->render('emplacement/show.html.twig', ['emplacement' => $emplacement]);
    }

    /**
     * @Route("/{id}/edit", name="emplacement_edit", methods="GET|POST")
     */
    public function edit(Request $request, Emplacement $emplacement): Response
    {
        $form = $this->createForm(EmplacementType::class, $emplacement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('emplacement_edit', ['id' => $emplacement->getId()]);
        }

        return $this->render('emplacement/edit.html.twig', [
            'emplacement' => $emplacement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="emplacement_delete", methods="DELETE")
     */
    public function delete(Request $request, Emplacement $emplacement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$emplacement->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($emplacement);
            $em->flush();
        }

        return $this->redirectToRoute('emplacement_index');
    }
}
