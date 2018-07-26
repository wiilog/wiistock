<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class PersonnalisationController extends Controller
{
    /**
     * @Route("/personnalisation", name="personnalisation")
     */
    public function index()
    {
        return $this->render('personnalisation/index.html.twig', [
            'controller_name' => 'PersonnalisationController',
        ]);
    }
}
