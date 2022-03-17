<?php

namespace App\Controller\Transport;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/ordre")]
class OrderController extends AbstractController {

    #[Route("/liste", name: "transport_order_index", methods: "GET")]
    public function index(): Response {
        // TODO
        return $this->render('transport/order/index.html.twig');
    }

}
