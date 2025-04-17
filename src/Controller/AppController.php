<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\Setting;
use App\Entity\Utilisateur;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route("/")]
class AppController extends AbstractController {

    #[Route("/accueil", name: "app_index")]
    public function index(): Response {
throw new \Exception("AAAAA");
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

    // Called to generate script tag in base.html.twig
    public function fontCSS(SettingsService        $settingsService,
                            EntityManagerInterface $entityManager): Response {

        $fontFamily = $settingsService->getValue($entityManager, Setting::FONT_FAMILY);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');
        $response->setContent("
            * {
                font-family: \"$fontFamily\" !important;
            }
        ");

        return $response;
    }

}
