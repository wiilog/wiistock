<?php

namespace App\Controller\SleepingStock;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route("/sleeping-stock", name: "sleeping_stock")]
class IndexController extends AbstractController {

    #[Route("/", name: "_index")]
    public function index(): Response {
        // TODO
        return $this->json([
            "success" => true,
        ]);
    }

}
