<?php

namespace App\Controller;

use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/stock/references_articles")
 */
class ReferencesArticlesController extends Controller
{
    /**
     * @Route("/", name="references_articles_index", methods="GET")
     */
    public function index(ReferencesArticlesRepository $referencesArticlesRepository): Response
    {
        return $this->render('references_articles/index.html.twig', ['references_articles' => $referencesArticlesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="references_articles_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $referencesArticle = new ReferencesArticles();
        $form = $this->createForm(ReferencesArticlesType::class, $referencesArticle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($referencesArticle);
            $em->flush();

            return $this->redirectToRoute('references_articles_index');
        }

        return $this->render('references_articles/new.html.twig', [
            'references_article' => $referencesArticle,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="references_articles_show", methods="GET")
     */
    public function show(ReferencesArticles $referencesArticle): Response
    {
        return $this->render('references_articles/show.html.twig', ['references_article' => $referencesArticle]);
    }

    /**
     * @Route("/{id}/edit", name="references_articles_edit", methods="GET|POST")
     */
    public function edit(Request $request, ReferencesArticles $referencesArticle): Response
    {
        $form = $this->createForm(ReferencesArticlesType::class, $referencesArticle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('references_articles_edit', ['id' => $referencesArticle->getId()]);
        }

        return $this->render('references_articles/edit.html.twig', [
            'references_article' => $referencesArticle,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="references_articles_delete", methods="DELETE")
     */
    public function delete(Request $request, ReferencesArticles $referencesArticle): Response
    {
        if ($this->isCsrfTokenValid('delete'.$referencesArticle->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($referencesArticle);
            $em->flush();
        }

        return $this->redirectToRoute('references_articles_index');
    }
}
