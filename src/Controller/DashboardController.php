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
     * @Route("/accueil", name="dashboards")
     */
    public function dashboards(DashboardSettingsService $dashboardSettingsService, EntityManagerInterface $manager): Response {
        return $this->render("dashboard/dashboards.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($manager),
        ]);
    }

    /**
     * @Route("/dashboard/externe", name="dashboards_external")
     */
    public function external(DashboardSettingsService $dashboardSettingsService, EntityManagerInterface $manager): Response {
        return $this->render("dashboard/external.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($manager),
            "title" => "Dashboard externe"
        ]);
    }

    /**
     * @Route("/dashboard/actualiser", name="dashboards_fetch", options={"expose"=true})
     */
    public function fetch(DashboardSettingsService $dashboardSettingsService, EntityManagerInterface $manager): Response {
        return $this->json([
            "dashboards" => $dashboardSettingsService->serialize($manager),
        ]);
    }

}
