<?php

namespace App\Controller\Transport;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/sous-traitance")]
class SubcontractController extends AbstractController {

    #[Route("/liste", name: "transport_subcontract_index", methods: "GET")]
    public function index(): Response {
        // TODO
        return $this->render('transport/subcontract/index.html.twig');
    }

}
