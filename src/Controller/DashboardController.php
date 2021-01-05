<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\DashboardService;
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
    public function dashboards(DashboardService $dashboardService,
                               DashboardSettingsService $dashboardSettingsService,
                               EntityManagerInterface $manager): Response {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        return $this->render("dashboard/dashboards.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($manager, $loggedUser, DashboardSettingsService::MODE_DISPLAY),
            "refreshed" => $dashboardService->refreshDate($manager),
        ]);
    }

    /**
     * @Route("/dashboard/{token}", name="dashboards_external", options={"expose"=true})
     */
    public function external(DashboardService $dashboardService,
                             DashboardSettingsService $dashboardSettingsService,
                             EntityManagerInterface $manager,
                             string $token): Response {
        if ($token != $_SERVER["APP_DASHBOARD_TOKEN"]) {
            return $this->redirectToRoute("access_denied");
        }

        return $this->render("dashboard/external.html.twig", [
            "title" => "Dashboard externe",
            "dashboards" => $dashboardSettingsService->serialize($manager, null, DashboardSettingsService::MODE_EXTERNAL),
            "refreshed" => $dashboardService->refreshDate($manager),
        ]);
    }

    /**
     * @Route("/dashboard/actualiser/{mode}", name="dashboards_fetch", options={"expose"=true})
     */
    public function fetch(DashboardService $dashboardService,
                          DashboardSettingsService $dashboardSettingsService,
                          EntityManagerInterface $manager,
                          int $mode): Response {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        return $this->json([
            "dashboards" => $dashboardSettingsService->serialize($manager, $loggedUser, $mode),
            "refreshed" => $dashboardService->refreshDate($manager),
        ]);
    }

}
