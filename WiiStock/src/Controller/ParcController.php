<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parc")
 */

class ParcController extends AbstractController
{
    
	/**
 	 * @Route("/list")
 	 */    
    public function index()
    {
        return $this->render('parc/index.html.twig', [
            'controller_name' => 'ParcController',
        ]);
    }


    public function create() 
    {
    	return $this->render('parc/create.html.twig', [
    		'controller_name' => 'ParcController',
    	]);
    }


    public function modify()
    {
    	return $this->render('parc/modify.html.twig', [
    		'controller_name' => 'ParcController',
    	]);
    }
}
