<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;

/**
 * @Route("/stock")
 */

class AccueilController extends Controller
{
    /**
     * @Route("/accueil", name="accueil")
     */
    public function index(ArticlesRepository $ArticlesRepository)
    {
        
        $today = date("d/m/Y");

        return $this->render('accueil/index.html.twig', [
            'date' => $today,
            'articles' => $ArticlesRepository->findAll(),
            'controller_name' => 'AccueilController',
        ]);
    }
}
