<?php

namespace App\Controller\Transport;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/planning")]
class PlanningController extends AbstractController {

    #[Route("/liste", name: "transport_planning_index", methods: "GET")]
    public function index(): Response {
        // TODO
        return $this->render('transport/planning/index.html.twig');
    }

}
