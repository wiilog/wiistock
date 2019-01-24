<?php

namespace App\Controller;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;
use Knp\Component\Pager\PaginatorInterface;
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
    public function index(ArticlesRepository $articlesRepository, PaginatorInterface $paginator, Request $request, $statut, $id): Response
    {   
        $pagination = $paginator->paginate(
            $articlesRepository->findByStatut($statut), /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            2
        );

        //liste des articles + action selon statut et si conforme requete SQL dédié "systéme de filtre"
        if (($statut !== 'mis en stock' && $id == 0) && $statut !== 'livré' && $statut !== 'all') 
        {
            return $this->render('articles/index.html.twig', ['articles'=> $pagination]);
        }
        else if($statut === 'mis en stock' && $id !== 0)
        {
            //Validation de la mise en stock/magasin
            $articles = $articlesRepository->findById($id);
            foreach ($articles as $article) {
                $article->setStatu('en stock'); 
                if($article->getDirection() !== null){//vérifie si la direction n'est pas nul, pour ne pas perdre l'emplacement si il y a des erreurs au niveau des receptions
                    $article->setPosition($article->getDirection());
                }
                $article->setDirection(null);
            }
            $this->getDoctrine()->getManager()->flush();

            return $this->render('articles/index.html.twig', ['articles'=> $pagination]); 

        /* 'demande de mise en stock' */
        }else if($statut === 'livré'){
            $articles = $articlesRepository->findById($id);
            foreach ($articles as $article) {
                $article->setStatu('destockage');
                $article->setDirection(null);
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->render('articles/index.html.twig', ['articles'=> $pagination]); 
            /* demande de sortie */
        }
        else
        {
            //chemin par défaut Basé sur un requete SQL basée sur l
           
            return $this->render('articles/index.html.twig', ['articles' => $paginator->paginate(
                $articlesRepository->findAll(),
                $request->query->getInt('page', 1),
                5
                )
            ]);
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
            $article->setStatu('en cours de reception');
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
            if ($article->getEtat() === false ){
                $article->setStatu('anomalie');
            }
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
        return $this->redirectToRoute('articles_index', ['statut' => 'all', 'id' => 0,]);
    }
}
