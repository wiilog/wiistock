<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\AverageRequestTime;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Dashboard\ComponentType;
use App\Entity\Demande;
use App\Entity\FiabilityByReference;
use App\Entity\Handling;
use App\Entity\LocationCluster;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Entity\Wiilock;
use App\Helper\Stream;
use App\Repository\AverageRequestTimeRepository;
use App\Service\DashboardSettingsService;
use App\Service\DateService;
use App\Service\DashboardService;
use App\Service\DemandeCollecteService;
use App\Service\DemandeLivraisonService;
use App\Service\HandlingService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class AccueilController extends AbstractController
{

    /**
     * @Route(
     *     "/statistiques/fiabilite-monetaire",
     *     name="get_monetary_fiability_statistics",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getMonetaryFiabilityStatistics(EntityManagerInterface $entityManager): Response
    {
        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $lastDayOfCurrentMonth = date("Y-m-d", strtotime("last day of this month", strtotime($firstDayOfCurrentMonth)));
        $precedentMonthFirst = $firstDayOfCurrentMonth;
        $precedentMonthLast = $lastDayOfCurrentMonth;
        $idx = 0;
        $value = [];
        $value['data'] = [];
        while ($idx !== 6) {
            $month = date("m", strtotime($precedentMonthFirst));
            $month = date("F", mktime(0, 0, 0, $month, 10));
            $totalEntryRefArticleOfPrecedentMonth = $mouvementStockRepository->countTotalEntryPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitRefArticleOfPrecedentMonth = $mouvementStockRepository->countTotalExitPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalRefArticleOfPrecedentMonth = $totalEntryRefArticleOfPrecedentMonth - $totalExitRefArticleOfPrecedentMonth;
            $totalEntryArticleOfPrecedentMonth = $mouvementStockRepository->countTotalEntryPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitArticleOfPrecedentMonth = $mouvementStockRepository->countTotalExitPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalArticleOfPrecedentMonth = $totalEntryArticleOfPrecedentMonth - $totalExitArticleOfPrecedentMonth;

            $nbrFiabiliteMonetaireOfPrecedentMonth = $totalRefArticleOfPrecedentMonth + $totalArticleOfPrecedentMonth;
            $month = str_replace(
                array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
                array('Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'),
                $month
            );
            $value['data'][$month] = $nbrFiabiliteMonetaireOfPrecedentMonth;
            $precedentMonthFirst = date("Y-m-d", strtotime("-1 month", strtotime($precedentMonthFirst)));
            $precedentMonthLast = date("Y-m-d", strtotime("last day of -1 month", strtotime($precedentMonthLast)));
            $idx += 1;
        }
        $value = array_reverse($value['data']);
        return new JsonResponse($value);
    }

    /**
     * @Route("/statistiques/graphique-reference", name="graph_ref", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function graphiqueReference(EntityManagerInterface $entityManager): Response
    {
        $fiabilityByReferenceRepository = $entityManager->getRepository(FiabilityByReference::class);

        $fiabiliteRef = $fiabilityByReferenceRepository->findAll();
        $value[] = [];
        foreach ($fiabiliteRef as $reference) {
            $date = $reference->getDate();
            $indicateur = $reference->getIndicateur();
            $dateTimeTostr = $date->format('Y-m-d');
            $month = date("m", strtotime($dateTimeTostr));
            $month = date("F", mktime(0, 0, 0, $month, 10));
            $value[] = [
                'mois' => $month,
                'nbr' => $indicateur
            ];
        }
        $data = $value;
        return new JsonResponse($data);
    }

    /**
     * @Route("/acceuil/dernier-rafraichissement", name="last_refresh", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param DashboardService $dashboardService
     * @return Response
     */
    public function getLastRefreshDate(DashboardService $dashboardService): Response
    {
        return new JsonResponse([
            'success' => true,
            'refreshDate' => $dashboardService->getLastRefresh(Wiilock::DASHBOARD_FED_KEY)
        ]);
    }

    /**
     * @Route(
     *     "/statistiques/arrivages-jour",
     *     name="get_daily_arrivals_statistics",
     *     options={"expose"=true},
     *      methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param DashboardService $dashboardService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function getDailyArrivalsStatistics(DashboardService $dashboardService,
                                               EntityManagerInterface $entityManager): Response
    {
        $colisCountByDay = $dashboardService->getChartData($entityManager, DashboardService::DASHBOARD_DOCK, 'arrivage-colis-daily');
        $arrivalCountByDays = $dashboardService->getChartData($entityManager, DashboardService::DASHBOARD_DOCK, 'arrivage-daily');
        $formattedColisData = $colisCountByDay ? $dashboardService->flatArray($colisCountByDay['data']) : [] ;
        $formattedArrivalData = $arrivalCountByDays ? $dashboardService->flatArray($arrivalCountByDays['data']) : [];
        return new JsonResponse([
            'data' => $formattedArrivalData,
            'subCounters' => $formattedColisData,
            'subLabel' => 'Colis',
            'label' => 'Arrivages'
        ]);
    }

    /**
     * @Route(
     *     "/statistiques/colis-jour",
     *     name="get_daily_packs_statistics",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param EntityManagerInterface $entityManager
     * @param DashboardService $dashboardService
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getDailyPacksStatistics(EntityManagerInterface $entityManager, DashboardService $dashboardService): Response
    {
        $data = $dashboardService->getChartData($entityManager, DashboardService::DASHBOARD_DOCK, 'colis');
        $formattedData = $data ? $dashboardService->flatArray($data['data']) : [];
        return new JsonResponse($formattedData);
    }

    /**
     * @Route(
     *     "/statistiques/arrivages-semaine",
     *     name="get_weekly_arrivals_statistics",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param DashboardService $dashboardService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function getWeeklyArrivalsStatistics(DashboardService $dashboardService,
                                                EntityManagerInterface $entityManager): Response
    {
        $colisCountByWeek = $dashboardService->getChartData($entityManager, DashboardService::DASHBOARD_DOCK, 'arrivage-colis-weekly');
        $arrivalCountByWeek = $dashboardService->getChartData($entityManager, DashboardService::DASHBOARD_DOCK, 'arrivage-weekly');
        $formattedColisData = $colisCountByWeek ? $dashboardService->flatArray($colisCountByWeek['data']) : [];
        $formattedArrivalData = $arrivalCountByWeek ? $dashboardService->flatArray($arrivalCountByWeek['data']) : [];
        return new JsonResponse([
            'data' => $formattedArrivalData,
            'subCounters' => $formattedColisData,
            'subLabel' => 'Colis',
            'label' => 'Arrivages'
        ]);
    }

    /**
     * @Route(
     *     "/statistiques/encours-par-duree-et-nature/{graph}",
     *     name="get_encours_count_by_nature_and_timespan",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     *
     * @param EntityManagerInterface $entityManager
     * @param DashboardService $dashboardService
     * @param int $graph
     *
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getEnCoursCountByNatureAndTimespan(EntityManagerInterface $entityManager,
                                                       DashboardService $dashboardService,
                                                       int $graph): Response
    {
        $neededOrder = [
            'Retard' => 0,
            'Moins d\'1h' => 1,
            '1h-6h' => 2,
            '6h-12h' => 3,
            '12h-24h' => 4,
            '24h-36h' => 5,
            '36h-48h' => 6,
        ];
        $graphToCode = [
            1 => LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_1,
            2 => LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_2
        ];

        $key = $graphToCode[$graph];
        $data = $dashboardService->getChartData($entityManager, DashboardService::DASHBOARD_ADMIN, $key);
        $orderedData = [];
        $orderedData['chartColors'] = $data['chartColors'] ?? [];
        $orderedData['total'] = $data['total'] ?? [];
        $orderedData['location'] = $data['location']  ?? [];
        if (!is_null($data)) {
            foreach ($data['data'] as $key => $datum) {
                $index = $neededOrder[$key];
                $orderedData['data'][$index] = $datum;
            }
        ksort($orderedData['data']);
        $orderedData['data'] = array_combine(array_keys($neededOrder), array_values($orderedData['data']));
        }
        return new JsonResponse($orderedData);
    }

    /**
     * @Route(
     *     "/statistiques/reception-admin",
     *     name="get_indicators_reception_admin",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param DashboardService $dashboardService
     * @return Response
     */
    public function getIndicatorsAdminReception(DashboardService $dashboardService): Response
    {
        $response = $dashboardService->getSimplifiedDataForAdminDashboard();
        return new JsonResponse($response);
    }

    /**
     * @Route(
     *     "/statistiques/monitoring-emballage",
     *     name="get_indicators_monitoring_packaging",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     * @param DashboardService $dashboardService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getIndicatorsMonitoringPackaging(DashboardService $dashboardService, EntityManagerInterface $entityManager): Response
    {
        $response = $dashboardService->getSimplifiedDataForPackagingDashboard($entityManager);
        return new JsonResponse($response);
    }

    /**
     * @Route(
     *     "/statistiques/reception-quai",
     *     name="get_indicators_reception_dock",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     * @param DashboardService $dashboardService
     * @return Response
     */
    public function getIndicatorsDockReception(DashboardService $dashboardService): Response
    {
        $response = $dashboardService->getSimplifiedDataForDockDashboard();
        return new JsonResponse($response);
    }

    /**
     * @Route(
     *     "/statistiques/receptions-associations",
     *     name="get_asso_recep_statistics",
     *     options={"expose"=true},
     *     methods={"GET"},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DashboardSettingsService $dashboardSettingsService
     * @return Response
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
     * @Route(
     *     "/statistiques/arrivages-um",
     *     name="get_arrival_um_statistics",
     *     options={"expose"=true},
     *     methods={"GET"},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DashboardSettingsService $dashboardSettingsService
     * @return Response
     * @throws Exception
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

    /**
     * @Route(
     *     "/statistiques/transporteurs-jour",
     *     name="get_daily_carriers_statistics",
     *     options={"expose"=true},
     *     methods={"GET"},
     *     condition="request.isXmlHttpRequest()"
     * )
     *
     * @param DashboardService $dashboardService
     * @return Response
     *
     * @throws NonUniqueResultException
     */
    public function getDailyCarriersStatistics(DashboardService $dashboardService): Response
    {
        $carriersLabels = $dashboardService->getDailyArrivalCarriers();
        return new JsonResponse($carriersLabels);
    }
}
