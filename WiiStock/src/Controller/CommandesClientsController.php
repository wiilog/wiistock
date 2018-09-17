<?php

namespace App\Controller;

use App\Entity\CommandesClients;
use App\Form\CommandesClientsType;
use App\Repository\CommandesClientsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/commandes/clients")
 */
class CommandesClientsController extends Controller
{
    /**
     * @Route("/", name="commandes_clients_index", methods="GET")
     */
    public function index(CommandesClientsRepository $commandesClientsRepository): Response
    {
        return $this->render('commandes_clients/index.html.twig', ['commandes_clients' => $commandesClientsRepository->findAll()]);
    }

    /**
     * @Route("/new", name="commandes_clients_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $commandesClient = new CommandesClients();
        $form = $this->createForm(CommandesClientsType::class, $commandesClient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($commandesClient);
            $em->flush();

            return $this->redirectToRoute('commandes_clients_index');
        }

        return $this->render('commandes_clients/new.html.twig', [
            'commandes_client' => $commandesClient,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="commandes_clients_show", methods="GET")
     */
    public function show(CommandesClients $commandesClient): Response
    {
        return $this->render('commandes_clients/show.html.twig', ['commandes_client' => $commandesClient]);
    }

    /**
     * @Route("/{id}/edit", name="commandes_clients_edit", methods="GET|POST")
     */
    public function edit(Request $request, CommandesClients $commandesClient): Response
    {
        $form = $this->createForm(CommandesClientsType::class, $commandesClient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('commandes_clients_edit', ['id' => $commandesClient->getId()]);
        }

        return $this->render('commandes_clients/edit.html.twig', [
            'commandes_client' => $commandesClient,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="commandes_clients_delete", methods="DELETE")
     */
    public function delete(Request $request, CommandesClients $commandesClient): Response
    {
        if ($this->isCsrfTokenValid('delete'.$commandesClient->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($commandesClient);
            $em->flush();
        }

        return $this->redirectToRoute('commandes_clients_index');
    }
}
