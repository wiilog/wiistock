<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Route("/referentiel")
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
        ]);
    }

    /**
     * @Route("/articles", name="referentiel_articles")
     */
    public function articles()
    {
        return $this->render('referentiel/articles.html.twig', [
            'controller_name' => 'ReferentielController',
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
