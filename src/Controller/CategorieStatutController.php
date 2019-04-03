<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/categorie-statut")
 */
class CategorieStatutController extends AbstractController
{
    /**
     * @Route("/", name="categorie_statut_index")
     */
    public function index()
    {
        return $this->render('categorie_statut/index.html.twig', [
            'controller_name' => 'CategorieStatutController',
        ]);
    }
}
