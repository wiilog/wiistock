<?php

namespace App\Controller;

use App\Entity\Themes;
use App\Form\ThemesType;
use App\Repository\ThemesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/themes")
 */
class ThemesController extends Controller
{
    /**
     * @Route("/", name="themes_index", methods="GET")
     */
    public function index(ThemesRepository $themesRepository): Response
    {
        return $this->render('themes/index.html.twig', ['themes' => $themesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="themes_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $theme = new Themes();
        $form = $this->createForm(ThemesType::class, $theme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($theme);
            $em->flush();

            return $this->redirectToRoute('themes_index');
        }

        return $this->render('themes/new.html.twig', [
            'theme' => $theme,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="themes_show", methods="GET")
     */
    public function show(Themes $theme): Response
    {
        return $this->render('themes/show.html.twig', ['theme' => $theme]);
    }

    /**
     * @Route("/{id}/edit", name="themes_edit", methods="GET|POST")
     */
    public function edit(Request $request, Themes $theme): Response
    {
        $form = $this->createForm(ThemesType::class, $theme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('themes_edit', ['id' => $theme->getId()]);
        }

        return $this->render('themes/edit.html.twig', [
            'theme' => $theme,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="themes_delete", methods="DELETE")
     */
    public function delete(Request $request, Themes $theme): Response
    {
        if ($this->isCsrfTokenValid('delete'.$theme->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($theme);
            $em->flush();
        }

        return $this->redirectToRoute('themes_index');
    }
}
