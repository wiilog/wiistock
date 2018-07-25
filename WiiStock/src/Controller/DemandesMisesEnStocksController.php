<?php

namespace App\Controller;

use App\Entity\DemandesMisesEnStocks;
use App\Form\DemandesMisesEnStocksType;
use App\Repository\DemandesMisesEnStocksRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/demandes/mises/en/stocks")
 */
class DemandesMisesEnStocksController extends Controller
{
    /**
     * @Route("/", name="demandes_mises_en_stocks_index", methods="GET")
     */
    public function index(DemandesMisesEnStocksRepository $demandesMisesEnStocksRepository): Response
    {
        return $this->render('demandes_mises_en_stocks/index.html.twig', ['demandes_mises_en_stocks' => $demandesMisesEnStocksRepository->findAll()]);
    }

    /**
     * @Route("/new", name="demandes_mises_en_stocks_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $demandesMisesEnStock = new DemandesMisesEnStocks();
        $form = $this->createForm(DemandesMisesEnStocksType::class, $demandesMisesEnStock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($demandesMisesEnStock);
            $em->flush();

            return $this->redirectToRoute('demandes_mises_en_stocks_index');
        }

        return $this->render('demandes_mises_en_stocks/new.html.twig', [
            'demandes_mises_en_stock' => $demandesMisesEnStock,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_mises_en_stocks_show", methods="GET")
     */
    public function show(DemandesMisesEnStocks $demandesMisesEnStock): Response
    {
        return $this->render('demandes_mises_en_stocks/show.html.twig', ['demandes_mises_en_stock' => $demandesMisesEnStock]);
    }

    /**
     * @Route("/{id}/edit", name="demandes_mises_en_stocks_edit", methods="GET|POST")
     */
    public function edit(Request $request, DemandesMisesEnStocks $demandesMisesEnStock): Response
    {
        $form = $this->createForm(DemandesMisesEnStocksType::class, $demandesMisesEnStock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('demandes_mises_en_stocks_edit', ['id' => $demandesMisesEnStock->getId()]);
        }

        return $this->render('demandes_mises_en_stocks/edit.html.twig', [
            'demandes_mises_en_stock' => $demandesMisesEnStock,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demandes_mises_en_stocks_delete", methods="DELETE")
     */
    public function delete(Request $request, DemandesMisesEnStocks $demandesMisesEnStock): Response
    {
        if ($this->isCsrfTokenValid('delete'.$demandesMisesEnStock->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($demandesMisesEnStock);
            $em->flush();
        }

        return $this->redirectToRoute('demandes_mises_en_stocks_index');
    }
}
