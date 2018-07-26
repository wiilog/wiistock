<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Route("/demande")
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
}
