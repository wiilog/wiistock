<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AccueilController extends Controller
{
    /**
     * @Route("/accueil", name="accueil")
     */
    public function index()
    {

        $today = date("d/m/Y");

        return $this->render('accueil/index.html.twig', [
            'date' => $today,
            'controller_name' => 'AccueilController',
        ]);
    }
}
