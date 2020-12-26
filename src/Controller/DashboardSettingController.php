<?php

namespace App\Controller;

use App\Service\DashboardSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage-global")
 */
class DashboardSettingController extends AbstractController {

    /**
     * @Route("/dashboard", name="dashboard_settings")
     * @param DashboardSettingsService $service
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function settings(DashboardSettingsService $service,
                             EntityManagerInterface $entityManager): Response {
        return $this->render("dashboard/settings.html.twig", [
            "dashboards" => $service->serialize($entityManager),
        ]);
    }

    /**
     * @Route("/dashboard/save", name="save_dashboard_settings", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DashboardSettingsService $dashboardSettingsService
     * @return Response
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager,
                         DashboardSettingsService $dashboardSettingsService): Response {
        $dashboards = json_decode($request->request->get("dashboards"), true);

        try {
            $dashboardSettingsService->treatJsonDashboard($entityManager, $dashboards);
        }
        catch(InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $unknownComponentCode = DashboardSettingsService::UNKNOWN_COMPONENT;
            if (preg_match("/$unknownComponentCode-(.*)/", $message, $matches)) {
                $unknownComponentLabel = $matches[1] ?? '';
                return $this->json([
                    "success" => false,
                    "msg" => "Type de composant ${unknownComponentLabel} inconnu"
                ]);
            }
            else {
                throw $exception;
            }
        }

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "dashboards" => $dashboardSettingsService->serialize($entityManager),
        ]);
    }

}
