<?php

namespace App\Controller;

use App\Entity\CategoriesVehicules;
use App\Form\CategoriesVehiculesType;
use App\Repository\CategoriesVehiculesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parc/categories_vehicules")
 */
class CategoriesVehiculesController extends Controller
{
    /**
     * @Route("/", name="categories_vehicules_index", methods="GET")
     */
    public function index(CategoriesVehiculesRepository $categoriesVehiculesRepository): Response
    {
        return $this->render('categories_vehicules/index.html.twig', ['categories_vehicules' => $categoriesVehiculesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="categories_vehicules_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $categoriesVehicule = new CategoriesVehicules();
        $form = $this->createForm(CategoriesVehiculesType::class, $categoriesVehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($categoriesVehicule);
            $em->flush();

            return $this->redirectToRoute('categories_vehicules_index');
        }

        return $this->render('categories_vehicules/new.html.twig', [
            'categories_vehicule' => $categoriesVehicule,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="categories_vehicules_show", methods="GET")
     */
    public function show(CategoriesVehicules $categoriesVehicule): Response
    {
        return $this->render('categories_vehicules/show.html.twig', ['categories_vehicule' => $categoriesVehicule]);
    }

    /**
     * @Route("/{id}/edit", name="categories_vehicules_edit", methods="GET|POST")
     */
    public function edit(Request $request, CategoriesVehicules $categoriesVehicule): Response
    {
        $form = $this->createForm(CategoriesVehiculesType::class, $categoriesVehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('categories_vehicules_edit', ['id' => $categoriesVehicule->getId()]);
        }

        return $this->render('categories_vehicules/edit.html.twig', [
            'categories_vehicule' => $categoriesVehicule,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="categories_vehicules_delete", methods="DELETE")
     */
    public function delete(Request $request, CategoriesVehicules $categoriesVehicule): Response
    {
        if ($this->isCsrfTokenValid('delete'.$categoriesVehicule->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($categoriesVehicule);
            $em->flush();
        }

        return $this->redirectToRoute('categories_vehicules_index');
    }
}
