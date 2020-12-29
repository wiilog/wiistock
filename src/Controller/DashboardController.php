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
     * @Route("/accueil-futur", name="dashboards")
     */
    public function dashboards(DashboardSettingsService $dashboardSettingsService, EntityManagerInterface $entityManager): Response {
        return $this->render("dashboard/dashboards.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($entityManager),
        ]);
    }

}
