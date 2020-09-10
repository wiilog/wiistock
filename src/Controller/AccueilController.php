<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiabilityByReference;
use App\Entity\Handling;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Service\DashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/")
 */
class AccueilController extends AbstractController
{

    /**
     * @Route("/accueil", name="accueil", methods={"GET"})
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        $data = $this->getDashboardData($entityManager);
        return $this->render('accueil/index.html.twig', $data);
    }

    /**
     * @Route(
     *     "/dashboard-externe/{token}/{page}",
     *     name="dashboard_ext",
     *     methods={"GET"},
     *     requirements={
     *         "page" = "(quai)|(admin)|(emballage)",
     *         "token" = "%dashboardToken%"
     *     }
     * )
     * @param EntityManagerInterface $entityManager
     * @param DashboardService $dashboardService
     * @param string $page
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function dashboardExt(EntityManagerInterface $entityManager,
                                 DashboardService $dashboardService,
                                 string $page): Response
    {
        $data = $this->getDashboardData($entityManager, true);
        $data['page'] = $page;
        $data['pageData'] = ($page === 'emballage')
            ? $dashboardService->getSimplifiedDataForPackagingDashboard($entityManager)
            : [];
        $data['refreshDate'] = $dashboardService->getLastRefresh();
        return $this->render('accueil/dashboardExt.html.twig', $data);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param bool $isDashboardExt
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function getDashboardData(EntityManagerInterface $entityManager,
                                      bool $isDashboardExt = false)
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $collecteRepository = $entityManager->getRepository(Collecte::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);

        $nbAlerts = $referenceArticleRepository->countAlert();

        $types = [
            MouvementStock::TYPE_INVENTAIRE_ENTREE,
            MouvementStock::TYPE_INVENTAIRE_SORTIE
        ];
        $nbStockInventoryMouvements = $mouvementStockRepository->countByTypes($types);
        $nbActiveRefAndArt = $referenceArticleRepository->countActiveTypeRefRef() + $articleRepository->countActiveArticles();
        $nbrFiabiliteReference = $nbActiveRefAndArt == 0 ? 0 : (1 - ($nbStockInventoryMouvements / $nbActiveRefAndArt)) * 100;

        $firstDayOfThisMonth = date("Y-m-d", strtotime("first day of this month"));

        $nbStockInventoryMouvementsOfThisMonth = $mouvementStockRepository->countByTypes($types, $firstDayOfThisMonth);
        $nbActiveRefAndArtOfThisMonth = $referenceArticleRepository->countActiveTypeRefRef() + $articleRepository->countActiveArticles();
        $nbrFiabiliteReferenceOfThisMonth = $nbActiveRefAndArtOfThisMonth == 0 ? 0 :
            (1 - ($nbStockInventoryMouvementsOfThisMonth / $nbActiveRefAndArtOfThisMonth)) * 100;

        $totalEntryRefArticleCurrent = $mouvementStockRepository->countTotalEntryPriceRefArticle();
        $totalExitRefArticleCurrent = $mouvementStockRepository->countTotalExitPriceRefArticle();
        $totalRefArticleCurrent = $totalEntryRefArticleCurrent - $totalExitRefArticleCurrent;
        $totalEntryArticleCurrent = $mouvementStockRepository->countTotalEntryPriceArticle();
        $totalExitArticleCurrent = $mouvementStockRepository->countTotalExitPriceArticle();
        $totalArticleCurrent = $totalEntryArticleCurrent - $totalExitArticleCurrent;
        $nbrFiabiliteMonetaire = $totalRefArticleCurrent + $totalArticleCurrent;

        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $totalEntryRefArticleOfThisMonth = $mouvementStockRepository->countTotalEntryPriceRefArticle($firstDayOfCurrentMonth);
        $totalExitRefArticleOfThisMonth = $mouvementStockRepository->countTotalExitPriceRefArticle($firstDayOfCurrentMonth);
        $totalRefArticleOfThisMonth = $totalEntryRefArticleOfThisMonth - $totalExitRefArticleOfThisMonth;
        $totalEntryArticleOfThisMonth = $mouvementStockRepository->countTotalEntryPriceArticle($firstDayOfCurrentMonth);
        $totalExitArticleOfThisMonth = $mouvementStockRepository->countTotalExitPriceArticle($firstDayOfCurrentMonth);
        $totalArticleOfThisMonth = $totalEntryArticleOfThisMonth - $totalExitArticleOfThisMonth;
        $nbrFiabiliteMonetaireOfThisMonth = $totalRefArticleOfThisMonth + $totalArticleOfThisMonth;

        $statutCollecte = $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER);
        $nbrDemandeCollecte = $collecteRepository->countByStatut($statutCollecte);

        $statutDemandeAT = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $nbrDemandeLivraisonAT = $demandeRepository->countByStatut($statutDemandeAT);

        $listStatutDemandeP = $statutRepository->getIdByCategorieNameAndStatusesNames(Demande::CATEGORIE, [Demande::STATUT_PREPARE, Demande::STATUT_INCOMPLETE]);
        $nbrDemandeLivraisonP = $demandeRepository->countByStatusesId($listStatutDemandeP);

        $handlingCounterToTreat = $handlingRepository->countHandlingToTreat();
        return [
            'nbAlerts' => $nbAlerts,
            'visibleDashboards' => $isDashboardExt
                ? []
                : $this->getUser()->getRole()->getDashboardsVisible(),
            'nbDemandeCollecte' => $nbrDemandeCollecte,
            'nbDemandeLivraisonAT' => $nbrDemandeLivraisonAT,
            'nbDemandeLivraisonP' => $nbrDemandeLivraisonP,
            'nbDemandeHandlingAT' => $handlingCounterToTreat,
            'nbrFiabiliteReference' => $nbrFiabiliteReference,
            'nbrFiabiliteMonetaire' => $nbrFiabiliteMonetaire,
            'nbrFiabiliteMonetaireOfThisMonth' => $nbrFiabiliteMonetaireOfThisMonth,
            'nbrFiabiliteReferenceOfThisMonth' => $nbrFiabiliteReferenceOfThisMonth,
            'status' => [
                'DLtoTreat' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_A_TRAITER),
                'DLincomplete' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_INCOMPLETE),
                'DLprepared' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_PREPARE),
                'DCToTreat' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_A_TRAITER),
                'handlingToTreat' => implode(', ', $statutRepository->getIdNotTreatedByCategory(CategorieStatut::HANDLING))
            ],
            'firstDayOfWeek' => date("d/m/Y", strtotime('monday this week')),
            'lastDayOfWeek' => date("d/m/Y", strtotime('sunday this week'))
        ];
    }

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
            'date' => $dashboardService->getLastRefresh()
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
     * @throws NoResultException
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
     * @throws NoResultException
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
        $key = DashboardService::DASHBOARD_ADMIN . '-' . $graph;
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
     * @param DashboardService $dashboardService
     * @return Response
     */
    public function getAssoRecepStatistics(Request $request,
                                           DashboardService $dashboardService): Response
    {
        $query = $request->query;
        $data = $dashboardService->getWeekAssoc(
            $query->get('firstDay'),
            $query->get('lastDay'),
            $query->get('beforeAfter')
        );
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
     * @param DashboardService $dashboardService
     * @return Response
     */
    public function getArrivalUmStatistics(Request $request,
                                           DashboardService $dashboardService): Response
    {
        $query = $request->query;
        $data = $dashboardService->getWeekArrival(
            $query->get('firstDay'),
            $query->get('lastDay'),
            $query->get('beforeAfter')
        );
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
