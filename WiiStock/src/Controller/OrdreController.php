<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Route("/ordre")
*/
class OrdreController extends Controller
{
    /**
     * @Route("/workflow", name="ordre_workflow")
     */
    public function workflow()
    {
        return $this->render('ordre/workflow.html.twig', [
            'controller_name' => 'OrdreController',
        ]);
    }
    
    /**
     * @Route("/reception", name="ordre_reception")
     */
    public function reception()
    {
        return $this->render('ordre/reception.html.twig', [
            'controller_name' => 'OrdreController',
        ]);
    }

    /**
     * @Route("/preparation", name="ordre_preparation")
     */
    public function preparation()
    {
        return $this->render('ordre/preparation.html.twig', [
            'controller_name' => 'OrdreController',
        ]);
    }
}
