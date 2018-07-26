<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
* @Route("/stock")
*/
class StockController extends Controller
{
    /**
     * @Route("/", name="stock")
     */
    public function index()
    {
        return $this->render('stock/index.html.twig', [
            'controller_name' => 'StockController',
        ]);
    }

    /**
    * @Route("/visu2D", name="visulaisation_2D")
    */
    public function visu2D()
    {
    	return $this->render('stock/visu2D.html.twig', [
    		'controller_name' => 'StockController',
    	]);
    }

    /**
    * @Route("/valorisation", name="valorisation")
    */
    public function valorisation()
    {
    	return $this->render('stock/valorisation.html.twig', [
    		'controller_name' => 'StockController',
    	]);
    }
}
