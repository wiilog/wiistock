<?php

namespace App\Controller;

use App\Entity\Allees;
use App\Entity\Travees;
use App\Entity\Racks;
use App\Entity\Entrepots;
use App\Entity\Emplacements;
use App\Form\AlleesType;
use App\Repository\AlleesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/stock/allees")
 */
class AlleesController extends Controller
{
    /**
     * @Route("/remove", name="allees_remove")
     */
    public function remove(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $id = $request->request->get('id');
            $allee = $this->getDoctrine()->getRepository(Allees::class)->find($id);
            $em = $this->getDoctrine()->getManager();
            $em->remove($allee);
            $em->flush();

            return new Response();
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/", name="allees_index", methods="GET")
     */
    public function index(AlleesRepository $alleesRepository) : Response
    {
        return $this->render('allees/index.html.twig', ['allees' => $alleesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="allees_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $allee = new Allees();
        $form = $this->createForm(AlleesType::class, $allee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($allee);
            $em->flush();

            return $this->redirectToRoute('allees_index');
        }

        return $this->render('allees/new.html.twig', [
            'allee' => $allee,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="allees_show", methods="GET")
     */
    public function show(Allees $allee) : Response
    {
        return $this->render('allees/show.html.twig', ['allee' => $allee]);
    }

    /**
     * @Route("/{id}/edit", name="allees_edit", methods="GET|POST")
     */
    public function edit(Request $request, Allees $allee) : Response
    {
        $form = $this->createForm(AlleesType::class, $allee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('allees_edit', ['id' => $allee->getId()]);
        }

        return $this->render('allees/edit.html.twig', [
            'allee' => $allee,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="allees_delete", methods="DELETE")
     */
    public function delete(Request $request, Allees $allee) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $allee->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($allee);
            $em->flush();
        }

        return $this->redirectToRoute('allees_index');
    }

    /**
     * @Route("/add", name="allees_add", methods="GET|POST")
     */
    public function add(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $allee = new Allees();

            $nom = $request->request->get('allee');
            $travees = $request->request->get('travee');
            $racks = $request->request->get('rack');
            $emplacements = $request->request->get('emplacement');
            $id = $request->request->get('id');

            if (!$emplacements || intval($emplacements) < 0) {
                $emplacements = 1;
            }
            if (!$racks || intval($racks) < 0) {
                $racks = 0;
            }
            if (!$travees || intval($travees) < 0) {
                $travees = 0;
            }

            $em = $this->getDoctrine()->getManager();

            $allee->setNom($nom);
            $allee->setEntrepots($this->getDoctrine()->getRepository(Entrepots::class)->find($id));
            $em->persist($allee);
            for ($n = 0; $n < intval($travees); $n++) {
                $travee = new Travees();
                $travee->setNom("Travée " . ($n + 1));
                $travee->setAllees($allee);
                $em->persist($travee);
                for ($p = 0; $p < intval($racks); $p++) {
                    $rack = new Racks();
                    $rack->setNom("Rack " . ($p + 1));
                    for ($i = 0; $i < $emplacements; $i++) {
                        $emplacement = new Emplacements();
                        $emplacement->setNom('Emplacement ' . $i);
                        $rack->addEmplacement($emplacement);
                    }
                    $rack->setTravees($travee);
                    $em->persist($rack);
                }
            }
            $em->flush();
            return new JsonResponse($allee->getId());
        }
        throw new NotFoundHttpException('404 not found');
    }
}
