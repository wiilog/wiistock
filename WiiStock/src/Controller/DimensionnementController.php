<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DimensionnementController extends Controller
{
    /**
     * @Route("/stock/admin/dimensionnement", name="dimensionnement")
     */
    public function index()
    {
        return $this->render('dimensionnement/index.html.twig', [
            'controller_name' => 'DimensionnementController',
        ]);
    }
}
