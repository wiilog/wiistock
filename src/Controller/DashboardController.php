<?php

namespace App\Controller;

use App\Entity\Dashboard\ComponentType;
use App\Entity\LatePack;
use App\Entity\Utilisateur;
use App\Service\DashboardService;
use App\Service\DashboardSettingsService;
use App\Service\SpecificService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/")
 */
class DashboardController extends AbstractController {

    /**
     * Called in /index.html.twig
     */
    public function index(DashboardService $dashboardService,
                          DashboardSettingsService $dashboardSettingsService,
                          EntityManagerInterface $manager,
                          SpecificService $specificService): Response {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        $client  =  $specificService->getAppClient();

        return $this->render("dashboard/dashboards.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($manager, $loggedUser, DashboardSettingsService::MODE_DISPLAY),
            "refreshed" => $dashboardService->refreshDate($manager),
            "refresh_rate" => in_array($client, SpecificService::EVERY_MINUTE_REFRESH_RATE_CLIENTS) ? 1 : 5,
        ]);
    }

    /**
     * @Route("/dashboard/externe/{token}", name="dashboards_external", options={"expose"=true})
     * @param DashboardService $dashboardService
     * @param DashboardSettingsService $dashboardSettingsService
     * @param EntityManagerInterface $manager
     * @param string $token
     * @return Response
     */
    public function external(DashboardService $dashboardService,
                             DashboardSettingsService $dashboardSettingsService,
                             EntityManagerInterface $manager,
                             SpecificService $specificService,
                             string $token): Response {
        if ($token != $_SERVER["APP_DASHBOARD_TOKEN"]) {
            return $this->redirectToRoute("access_denied");
        }
        $client = $specificService->getAppClient();
        return $this->render("dashboard/external.html.twig", [
            "title" => "Dashboard externe", //ne s'affiche normalement jamais
            "dashboards" => $dashboardSettingsService->serialize($manager, null, DashboardSettingsService::MODE_EXTERNAL),
            "refreshed" => $dashboardService->refreshDate($manager),
            "client" => $client,
            "refresh_rate" => in_array($client, SpecificService::EVERY_MINUTE_REFRESH_RATE_CLIENTS) ? 1 : 5
        ]);
    }

    /**
     * @Route("/dashboard/sync/{mode}", name="dashboards_fetch", options={"expose"=true})
     * @param DashboardService $dashboardService
     * @param DashboardSettingsService $dashboardSettingsService
     * @param EntityManagerInterface $manager
     * @param int $mode
     * @return Response
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

    /**
     * @Route("/dashboard/statistics/late-pack-api", name="api_late_pack", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function apiLatePacks(EntityManagerInterface $entityManager): Response
    {
        $latePackRepository = $entityManager->getRepository(LatePack::class);
        $retards = $latePackRepository->findAllForDatatable();
        return new JsonResponse([
            'data' => $retards
        ]);
    }


    /**
     * @Route("/dashboard/statistics/receptions-associations", name="get_asso_recep_statistics", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     */
    public function getAssoRecepStatistics(Request $request,
                                           EntityManagerInterface $entityManager,
                                           DashboardSettingsService $dashboardSettingsService): Response
    {
        $componentTypeRepository = $entityManager->getRepository(ComponentType::class);
        $componentType = $componentTypeRepository->findOneBy([
            'meterKey' => ComponentType::RECEIPT_ASSOCIATION
        ]);
        $data = $dashboardSettingsService->serializeValues($entityManager, $componentType, $request->query->all());
        return new JsonResponse($data);
    }

    /**
     * @Route("/dashboard/statistics/arrivages-um", name="get_arrival_um_statistics", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     */
    public function getArrivalUmStatistics(Request $request,
                                           EntityManagerInterface $entityManager,
                                           DashboardSettingsService $dashboardSettingsService): Response
    {
        $componentTypeRepository = $entityManager->getRepository(ComponentType::class);
        $componentType = $componentTypeRepository->findOneBy([
            'meterKey' => ComponentType::DAILY_ARRIVALS
        ]);
        $data = $dashboardSettingsService->serializeValues($entityManager, $componentType, $request->query->all());
        return new JsonResponse($data);
    }


}
