<?php

namespace App\Controller;

use App\Entity\Allees;
use App\Entity\Travees;
use App\Entity\Racks;
use App\Entity\Entrepots;
use App\Entity\Emplacements;
use App\Form\TraveesType;
use App\Repository\TraveesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/stock/travees")
 */
class TraveesController extends Controller
{
    /**
     * @Route("/remove", name="travees_remove")
     */
    public function remove(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $id = $request->request->get('id');
            $travee = $this->getDoctrine()->getRepository(Travees::class)->find($id);
            $em = $this->getDoctrine()->getManager();
            $em->remove($travee);
            $em->flush();

            return new Response();
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/", name="travees_index", methods="GET")
     */
    public function index(TraveesRepository $traveesRepository) : Response
    {
        return $this->render('travees/index.html.twig', ['travees' => $traveesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="travees_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $travee = new Travees();
        $form = $this->createForm(TraveesType::class, $travee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($travee);
            $em->flush();

            return $this->redirectToRoute('travees_index');
        }

        return $this->render('travees/new.html.twig', [
            'travee' => $travee,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="travees_show", methods="GET")
     */
    public function show(Travees $travee) : Response
    {
        return $this->render('travees/show.html.twig', ['travee' => $travee]);
    }

    /**
     * @Route("/{id}/edit", name="travees_edit", methods="GET|POST")
     */
    public function edit(Request $request, Travees $travee) : Response
    {
        $form = $this->createForm(TraveesType::class, $travee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('travees_edit', ['id' => $travee->getId()]);
        }

        return $this->render('travees/edit.html.twig', [
            'travee' => $travee,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="travees_delete", methods="DELETE")
     */
    public function delete(Request $request, Travees $travee) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $travee->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($travee);
            $em->flush();
        }

        return $this->redirectToRoute('travees_index');
    }

    /**
     * @Route("/add", name="travees_add", methods="GET|POST")
     */
    public function add(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $travee = new Travees();

            $nom = $request->request->get('travee');
            $racks = $request->request->get('rack');
            $emplacements = $request->request->get('emplacement');
            $id = $request->request->get('id');

            if (!$emplacements || intval($emplacements) < 0) {
                $emplacements = 1;
            }
            if (!$racks || intval($racks) < 0) {
                $racks = 0;
            }

            $em = $this->getDoctrine()->getManager();

            $travee->setNom($nom);
            $travee->setAllees($this->getDoctrine()->getRepository(Allees::class)->find($id));
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
            $em->flush();
            return new JsonResponse($travee->getId());
        }
        throw new NotFoundHttpException('404 not found');
    }
}
