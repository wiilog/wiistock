<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/production', name: 'production_request_')]
class ProductionRequestController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function index(): Response {
        return $this->render('production_request/index.html.twig', [
            'controller_name' => 'ProductionRequestController',
        ]);
    }
}
