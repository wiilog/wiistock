<?php

namespace App\Controller;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Emplacements;

/**
 * @Route("/stock/articles")
 */
class ArticlesController extends Controller
{
    /**
     * @Route("/get", name="articles_get", methods="GET")
     */
    public function getArticles(Request $request, ArticlesRepository $articlesRepository) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $q = $request->query->get('q');
            $articles = $articlesRepository->findByDesignation($q);
            $rows = array();
            foreach ($articles as $article) {
                $row = [
                    "id" => $article->getId(),
                    "designation" => $article->getDesignation(),
                    "valeur" => $article->getValeur(),
                    "commentaire" => $article->getCommentaire(),
                    "emplacement" => $article->getEmplacement()->getNom(),
                    "reference" => $article->getReference(),
                    "photo_article" => $article->getPhoto(),
                ];
                array_push($rows, $row);
            }

            $data = array(
                "total_count" => count($rows),
                "items" => $rows,
            );
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/", name="articles_index", methods="GET")
     */
    public function index(ArticlesRepository $articlesRepository) : Response
    {
        return $this->render('articles/index.html.twig', ['articles' => $articlesRepository->findAll()]);
    }

    /**
     * @Route("/affichage/{id}", name="articles_affichage", methods="GET")
     */
    public function affichage(Articles $article) : Response
    {
        return $this->render('articles/affichage.html.twig', [
            'article' => $article,
        ]);
    }

    /**
     * @Route("/new", name="articles_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $article = new Articles();
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute('articles_index');
        }

        return $this->render('articles/new.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="articles_show", methods="GET")
     */
    public function show(Articles $article) : Response
    {
        return $this->render('articles/show.html.twig', ['article' => $article]);
    }

    /**
     * @Route("/{id}/edit", name="articles_edit", methods="GET|POST")
     */
    public function edit(Request $request, Articles $article) : Response
    {
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()]);
        }

        return $this->render('articles/edit.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="articles_delete", methods="DELETE")
     */
    public function delete(Request $request, Articles $article) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($article);
            $em->flush();
        }

        return $this->redirectToRoute('articles_index');
    }
}
