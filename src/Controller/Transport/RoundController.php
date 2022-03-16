<?php

namespace App\Controller\Transport;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/tournee")]
class RoundController extends AbstractController {

    #[Route("/liste", name: "transport_round_index", methods: "GET")]
    public function index(): Response {
        // TODO
        return $this->render('transport/round/index.html.twig');
    }

}
