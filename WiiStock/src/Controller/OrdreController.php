<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Repository\OrdresRepository;

/**
 * @Route("/stock/ordre")
*/
class OrdreController extends Controller
{
    /**
     * @Route("/workflow", name="ordre_workflow")
     */
    public function workflow(OrdresRepository $ordresRepository)
    {
        $ordres = $ordresRepository->findAll();

        return $this->render('ordre/workflow.html.twig', [
            'controller_name' => 'OrdreController',
            'ordres' => $ordres,
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

    /**
     * @Route("/creation", name="ordre_creation")
     */
    public function creation()
    {
        return $this->render('ordre/creation.html.twig', [
            'controller_name' => 'OrdreController',
        ]);
    }
}
