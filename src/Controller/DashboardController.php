<?php

namespace App\Controller;

use App\Service\DashboardSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/")
 */
class DashboardController extends AbstractController {

    /**
     * @Route("/accueil", name="accueil")
     */
    public function dashboards(DashboardSettingsService $dashboardSettingsService, EntityManagerInterface $manager): Response {
        return $this->render("dashboard/dashboards.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($manager, DashboardSettingsService::MODE_DISPLAY),
        ]);
    }

    /**
     * @Route("/dashboard/{token}", name="dashboards_external", options={"expose"=true})
     */
    public function external(DashboardSettingsService $dashboardSettingsService, EntityManagerInterface $manager, string $token): Response {
        if($token != $_SERVER["APP_DASHBOARD_TOKEN"]) {
            return $this->redirectToRoute("access_denied");
        }

        return $this->render("dashboard/external.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($manager, DashboardSettingsService::MODE_EXTERNAL),
            "title" => "Dashboard externe"
        ]);
    }

    /**
     * @Route("/dashboard/actualiser/{mode}", name="dashboards_fetch", options={"expose"=true})
     */
    public function fetch(DashboardSettingsService $dashboardSettingsService, EntityManagerInterface $manager, int $mode): Response {
        return $this->json([
            "dashboards" => $dashboardSettingsService->serialize($manager, $mode),
        ]);
    }

}
