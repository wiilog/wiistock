<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


use App\Repository\AlerteRepository;

/**
 * @Route("/stock")
 */

class AccueilController extends AbstractController
{
    /**
     * @Route("/accueil", name="accueil", methods={"GET"})
     */
    public function index(AlerteRepository $arlerteRepository, Request $request): Response
    {  
        $nbAlerteQ = $arlerteRepository->findCountAlerte();
        $nbAlerte = $nbAlerteQ[0];
        // $nbAlerte = 2;
        return $this->render('accueil/index.html.twig', [
            'nbAlerte' => $nbAlerte,
        ]);
    }
}
