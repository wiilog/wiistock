<?php

namespace App\Controller;

use App\Entity\CommandesFournisseurs;
use App\Form\CommandesFournisseursType;
use App\Repository\CommandesFournisseursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/commandes/fournisseurs")
 */
class CommandesFournisseursController extends Controller
{
    /**
     * @Route("/", name="commandes_fournisseurs_index", methods="GET")
     */
    public function index(CommandesFournisseursRepository $commandesFournisseursRepository): Response
    {
        return $this->render('commandes_fournisseurs/index.html.twig', ['commandes_fournisseurs' => $commandesFournisseursRepository->findAll()]);
    }

    /**
     * @Route("/new", name="commandes_fournisseurs_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $commandesFournisseur = new CommandesFournisseurs();
        $form = $this->createForm(CommandesFournisseursType::class, $commandesFournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($commandesFournisseur);
            $em->flush();

            return $this->redirectToRoute('commandes_fournisseurs_index');
        }

        return $this->render('commandes_fournisseurs/new.html.twig', [
            'commandes_fournisseur' => $commandesFournisseur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="commandes_fournisseurs_show", methods="GET")
     */
    public function show(CommandesFournisseurs $commandesFournisseur): Response
    {
        return $this->render('commandes_fournisseurs/show.html.twig', ['commandes_fournisseur' => $commandesFournisseur]);
    }

    /**
     * @Route("/{id}/edit", name="commandes_fournisseurs_edit", methods="GET|POST")
     */
    public function edit(Request $request, CommandesFournisseurs $commandesFournisseur): Response
    {
        $form = $this->createForm(CommandesFournisseursType::class, $commandesFournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('commandes_fournisseurs_edit', ['id' => $commandesFournisseur->getId()]);
        }

        return $this->render('commandes_fournisseurs/edit.html.twig', [
            'commandes_fournisseur' => $commandesFournisseur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="commandes_fournisseurs_delete", methods="DELETE")
     */
    public function delete(Request $request, CommandesFournisseurs $commandesFournisseur): Response
    {
        if ($this->isCsrfTokenValid('delete'.$commandesFournisseur->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($commandesFournisseur);
            $em->flush();
        }

        return $this->redirectToRoute('commandes_fournisseurs_index');
    }
}
