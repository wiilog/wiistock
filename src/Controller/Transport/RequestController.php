<?php

namespace App\Controller\Transport;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/demande")]
class RequestController extends AbstractController {

    #[Route("/liste", name: "transport_request_index", methods: "GET")]
    public function index(): Response {
        // TODO
        return $this->render('transport/request/index.html.twig');
    }

}
