<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class CategorieStatutController extends AbstractController
{
    /**
     * @Route("/categorie/statut", name="categorie_statut")
     */
    public function index()
    {
        return $this->render('categorie_statut/index.html.twig', [
            'controller_name' => 'CategorieStatutController',
        ]);
    }
}
