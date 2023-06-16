<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;
use Symfony\Component\HttpFoundation\Request;


#[Route("/")]
class AppController extends AbstractController {

    #[Route("/accueil", name: "app_index")]
    public function index(): Response {

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $landingPageController = match ($user?->getRole()?->getLandingPage()) {
            Role::LANDING_PAGE_TRANSPORT_PLANNING => Transport\PlanningController::class . '::index',
            Role::LANDING_PAGE_TRANSPORT_REQUEST => Transport\RequestController::class . '::index',
            // Role::LANDING_PAGE_DASHBOARD
            default => DashboardController::class . '::index'
        };

        return $this->render('index.html.twig', ['landingPageController' => $landingPageController]);
    }

}
