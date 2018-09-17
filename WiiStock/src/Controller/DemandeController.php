<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Route("/stock/demande")
*/
class DemandeController extends Controller
{
    /**
     * @Route("/workflow", name="demande_workflow")
     */
    public function workflow()
    {
        return $this->render('demande/workflow.html.twig', [
            'controller_name' => 'DemandeController',
        ]);
    }

	/**
     * @Route("/creation", name="demande_creation")
     */
    public function creation()
    {
        return $this->render('demande/creation.html.twig', [
            'controller_name' => 'DemandeController',
        ]);
    }    
}
