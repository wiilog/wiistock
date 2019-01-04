<?php

namespace App\Controller;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;



/**
 * @Route("/articles")
 */
class ArticlesController extends AbstractController
{
    /**
     * @Route("/index/{statut}/{id}", name="articles_index", methods="GET")
     */
    public function index(ArticlesRepository $articlesRepository, $statut, $id): Response
    {   
        //liste des articles + action selon statut requete SQL dédié "systéme de filtre"
        if ($statut ===  " demande de mise en stock") {
            return $this->render('articles/index.html.twig', ['articles'=> $articlesRepository->findByStatut($statut)]);
        }else if( $statut === "en cours de reception"){
            return $this->render('articles/index.html.twig', ['articles'=> $articlesRepository->findByStatut($statut)]);
        }else if($statut ==="en stock"){
            return $this->render('articles/index.html.twig', ['articles'=> $articlesRepository->findByStatut($statut)]);
        }else if($statut === 'mis en stock' && $id !== 0){
            //validation de la mise en stock/magasin
            $articles = $articlesRepository->findById($id);
            foreach ($articles as $article) {
                $article->setStatu('en stock');
            //vérifie si la direction n'est pas nul, pour ne pas perdre l'emplacement si il y a des erreur au niveau des receptions 
                if($article->getDirection() !== null){
                    $article->setPosition($article->getDirection());
                }
                $article->setDirection(null);
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->render('articles/index.html.twig', ['articles'=> $articlesRepository->findByStatut(' demande de mise en stock')]);  
        }else{
            //chemin par défaut 
            return $this->render('articles/index.html.twig', ['articles' => $articlesRepository->findAll()]);
        }
    }

    /**
     * @Route("/new", name="articles_new", methods="GET|POST")
     */
    public function new(Request $request): Response
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
     * @Route("/show/{id}", name="articles_show", methods="GET")
     */
    public function show(Articles $article): Response
    {
        return $this->render('articles/show.html.twig', ['article' => $article]);
    }

    /**
     * @Route("/edite/{id}", name="articles_edit", methods="GET|POST")
     */
    public function edit(Request $request, Articles $article): Response
    {
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('articles_index', ['statut' => 'all', 'id' => 0,]);
        }
        return $this->render('articles/edit.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
            'id' => $article->getReception()->getId(),
        ]);
    }

    /**
     * @Route("/{id}", name="articles_delete", methods="DELETE")
     */
    public function delete(Request $request, Articles $article): Response
    {
        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($article);
            $em->flush();
        }
        return $this->redirectToRoute('articles_index');
    }
}
