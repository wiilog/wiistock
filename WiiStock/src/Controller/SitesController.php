<?php

namespace App\Controller;

use App\Entity\Parcs;
use App\Entity\Sites;
use App\Form\SitesType;
use App\Repository\SitesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parc/admin/sites")
 */
class SitesController extends Controller
{
    /**
     * @Route("/", name="sites_index", methods="GET")
     */
    public function index(SitesRepository $sitesRepository) : Response
    {
        return $this->render('sites/index.html.twig', ['sites' => $sitesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="sites_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $site = new Sites();
        $form = $this->createForm(SitesType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($site);
            $em->flush();

            return $this->redirectToRoute('sites_index');
        }

        return $this->render('sites/new.html.twig', [
            'site' => $site,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="sites_show", methods="GET")
     */
    public function show(Sites $site) : Response
    {
        return $this->render('sites/show.html.twig', [
            'site' => $site,
            'parcs' => $site->getParcs(),
        ]);
    }

    /**
     * @Route("/{id}/edit", name="sites_edit", methods="GET|POST")
     */
    public function edit(Request $request, Sites $site) : Response
    {
        $filiale_init = $site->getFiliale();
        $form = $this->createForm(SitesType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $filiale = $form->getData()->getFiliale();
            $parcs = $em->getRepository(Parcs::class)->findAll(array('filiale' => $filiale_init->getId()));

            foreach ($parcs as $parc) {
                if ($filiale->getNom() != $filiale_init->getNom()) {
                    $parc->setFiliale($filiale);
                }
            }
            $em->flush();

            return $this->redirectToRoute('parc_parametrage');
        }

        return $this->render('sites/edit.html.twig', [
            'site' => $site,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="sites_delete", methods="DELETE")
     */
    public function delete(Request $request, Sites $site) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $site->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($site);
            $em->flush();
        }

        return $this->redirectToRoute('sites_index');
    }
}
