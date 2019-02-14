<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use App\Entity\Fournisseurs;
use App\Form\FournisseursType;
use App\Repository\FournisseursRepository;

use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/stock/referentiel")
 */
class ReferentielController extends Controller
{
    /**
     * @Route("/", name="referentiel")
     */
    public function index()
    {
        return $this->render('referentiel/index.html.twig', [
            'controller_name' => 'ReferentielController',
        ]);
    }

    /**
     * @Route("/clients", name="referentiel_clients")
     */
    public function clients()
    {
        return $this->render('referentiel/clients.html.twig', [
            'controller_name' => 'ReferentielController',
        ]);
    }

    /**
     * @Route("/fournisseurs", name="referentiel_fournisseurs")
     */
    public function fournisseurs()
    {
        return $this->render('referentiel/fournisseurs.html.twig', [
            'controller_name' => 'ReferentielController',
            'fournisseurs' => $this->getDoctrine()->getRepository(Fournisseurs::class)->findAll()
        ]);
    }

    /**
     * @Route("/articles", name="referentiel_articles")
     */
    public function articles(ReferencesArticlesRepository $referencesArticlesRepository, ArticlesRepository $articlesRepository)
    {
        $refArticles = $referencesArticlesRepository->findAll();
        foreach ($refArticles as $refArticle) {
            //on recupere seulement la quantite des articles requete SQL dédié
            $articleByRef = $articlesRepository->findQteByRefAndConf($refArticle);
            $quantityRef = 0;
            foreach ($articleByRef as $article) {
                $quantityRef ++;
            }
            $refArticle->setQuantity($quantityRef);  
        }
        $this->getDoctrine()->getManager()->flush();

        return $this->render('referentiel/articles.html.twig', [
            'controller_name' => 'ReferentielController',
            'articles' =>  $this->getDoctrine()->getRepository(ReferencesArticles::class)->findAll()
        ]);
    }

    /**
     * @Route("/categories", name="referentiel_categories")
     */
    public function categories()
    {
        return $this->render('referentiel/categories.html.twig', [
            'controller_name' => 'ReferentielController',
        ]);
    }
}
