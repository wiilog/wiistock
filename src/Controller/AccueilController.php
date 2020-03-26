<?php

namespace App\Controller;

use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\Colis;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\Manutention;
use App\Entity\MouvementStock;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\Statut;
use App\Service\DashboardService;
use App\Service\EnCoursService;
use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\EmplacementRepository;
use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\ManutentionRepository;
use App\Repository\AlerteExpiryRepository;
use App\Repository\ArticleRepository;
use App\Repository\FiabilityByReferenceRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\ReferenceArticleRepository;


/**
 * @Route("/")
 */
class AccueilController extends AbstractController
{

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var ManutentionRepository
     */
    private $manutentionRepository;

    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var AlerteExpiryRepository
     */
    private $alerteExpiryRepository;

    /**
     * @var fiabilityByReferenceRepository
     */
    private $fiabilityByReferenceRepository;

    /**
     * @var DashboardService
     */
    private $dashboardService;

    public function __construct(DashboardService $dashboardService,
                                ArticleRepository $articleRepository,
                                ReferenceArticleRepository $referenceArticleRepository,
                                AlerteExpiryRepository $alerteExpiryRepository,
                                ManutentionRepository $manutentionRepository,
                                DemandeRepository $demandeRepository,
                                CollecteRepository $collecteRepository,
                                EmplacementRepository $emplacementRepository,
                                MouvementStockRepository $mouvementStockRepository,
                                FiabilityByReferenceRepository $fiabilityByReferenceRepository)
    {
        $this->dashboardService = $dashboardService;
        $this->emplacementRepository = $emplacementRepository;
        $this->collecteRepository = $collecteRepository;
        $this->demandeRepository = $demandeRepository;
        $this->manutentionRepository = $manutentionRepository;
        $this->alerteExpiryRepository = $alerteExpiryRepository;
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->refArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->fiabilityByReferenceRepository = $fiabilityByReferenceRepository;
    }

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
     *         "page" = "(quai)|(admin)",
     *         "token" = "%dashboardToken%"
     *     }
     * )
     * @param EntityManagerInterface $entityManager
     * @param string $page
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function dashboardExt(EntityManagerInterface $entityManager,
                                 string $page): Response
    {
        $data = $this->getDashboardData($entityManager);
		$data['page'] = $page;
        return $this->render('accueil/dashboardExt.html.twig', $data);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function getDashboardData(EntityManagerInterface $entityManager)
    {
        $statutRepository = $entityManager->getRepository(Statut::class);

        $nbAlerts = $this->refArticleRepository->countAlert();

        $types = [
            MouvementStock::TYPE_INVENTAIRE_ENTREE,
            MouvementStock::TYPE_INVENTAIRE_SORTIE
        ];
        $nbStockInventoryMouvements = $this->mouvementStockRepository->countByTypes($types);
        $nbActiveRefAndArt = $this->refArticleRepository->countActiveTypeRefRef() + $this->articleRepository->countActiveArticles();
        $nbrFiabiliteReference = $nbActiveRefAndArt == 0 ? 0 : (1 - ($nbStockInventoryMouvements / $nbActiveRefAndArt)) * 100;

        $firstDayOfThisMonth = date("Y-m-d", strtotime("first day of this month"));

        $nbStockInventoryMouvementsOfThisMonth = $this->mouvementStockRepository->countByTypes($types, $firstDayOfThisMonth);
        $nbActiveRefAndArtOfThisMonth = $this->refArticleRepository->countActiveTypeRefRef() + $this->articleRepository->countActiveArticles();
        $nbrFiabiliteReferenceOfThisMonth = $nbActiveRefAndArtOfThisMonth == 0 ? 0 :
            (1 - ($nbStockInventoryMouvementsOfThisMonth / $nbActiveRefAndArtOfThisMonth)) * 100;

        $totalEntryRefArticleCurrent = $this->mouvementStockRepository->countTotalEntryPriceRefArticle();
        $totalExitRefArticleCurrent = $this->mouvementStockRepository->countTotalExitPriceRefArticle();
        $totalRefArticleCurrent = $totalEntryRefArticleCurrent - $totalExitRefArticleCurrent;
        $totalEntryArticleCurrent = $this->mouvementStockRepository->countTotalEntryPriceArticle();
        $totalExitArticleCurrent = $this->mouvementStockRepository->countTotalExitPriceArticle();
        $totalArticleCurrent = $totalEntryArticleCurrent - $totalExitArticleCurrent;
        $nbrFiabiliteMonetaire = $totalRefArticleCurrent + $totalArticleCurrent;

        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $totalEntryRefArticleOfThisMonth = $this->mouvementStockRepository->countTotalEntryPriceRefArticle($firstDayOfCurrentMonth);
        $totalExitRefArticleOfThisMonth = $this->mouvementStockRepository->countTotalExitPriceRefArticle($firstDayOfCurrentMonth);
        $totalRefArticleOfThisMonth = $totalEntryRefArticleOfThisMonth - $totalExitRefArticleOfThisMonth;
        $totalEntryArticleOfThisMonth = $this->mouvementStockRepository->countTotalEntryPriceArticle($firstDayOfCurrentMonth);
        $totalExitArticleOfThisMonth = $this->mouvementStockRepository->countTotalExitPriceArticle($firstDayOfCurrentMonth);
        $totalArticleOfThisMonth = $totalEntryArticleOfThisMonth - $totalExitArticleOfThisMonth;
        $nbrFiabiliteMonetaireOfThisMonth = $totalRefArticleOfThisMonth + $totalArticleOfThisMonth;

        $statutCollecte = $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER);
        $nbrDemandeCollecte = $this->collecteRepository->countByStatut($statutCollecte);

        $statutDemandeAT = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $nbrDemandeLivraisonAT = $this->demandeRepository->countByStatut($statutDemandeAT);

        $listStatutDemandeP = $statutRepository->getIdByCategorieNameAndStatusesNames(Demande::CATEGORIE, [Demande::STATUT_PREPARE, Demande::STATUT_INCOMPLETE]);
        $nbrDemandeLivraisonP = $this->demandeRepository->countByStatusesId($listStatutDemandeP);

        $statutManutAT = $statutRepository->findOneByCategorieNameAndStatutCode(Manutention::CATEGORIE, Manutention::STATUT_A_TRAITER);
        $nbrDemandeManutentionAT = $this->manutentionRepository->countByStatut($statutManutAT);

        return [
            'nbAlerts' => $nbAlerts,
            'nbDemandeCollecte' => $nbrDemandeCollecte,
            'nbDemandeLivraisonAT' => $nbrDemandeLivraisonAT,
            'nbDemandeLivraisonP' => $nbrDemandeLivraisonP,
            'nbDemandeManutentionAT' => $nbrDemandeManutentionAT,
            'emplacements' => $this->emplacementRepository->findAll(),
            'nbrFiabiliteReference' => $nbrFiabiliteReference,
            'nbrFiabiliteMonetaire' => $nbrFiabiliteMonetaire,
            'nbrFiabiliteMonetaireOfThisMonth' => $nbrFiabiliteMonetaireOfThisMonth,
            'nbrFiabiliteReferenceOfThisMonth' => $nbrFiabiliteReferenceOfThisMonth,
            'status' => [
                'DLtoTreat' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_A_TRAITER),
                'DLincomplete' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_INCOMPLETE),
                'DLprepared' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_PREPARE),
                'DCToTreat' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_A_TRAITER),
                'MToTreat' => $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::MANUTENTION, Manutention::STATUT_A_TRAITER)
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
     */
    public function getMonetaryFiabilityStatistics(): Response
    {
        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $lastDayOfCurrentMonth = date("Y-m-d", strtotime("last day of this month", strtotime($firstDayOfCurrentMonth)));
        $precedentMonthFirst = $firstDayOfCurrentMonth;
        $precedentMonthLast = $lastDayOfCurrentMonth;
        $idx = 0;
        $value = [];
        $value['data'] = [];
        while ($idx !== 6) {
            $month = date("m", strtotime($precedentMonthFirst));
            $month = date("F", mktime(0,0,0, $month, 10));
            $totalEntryRefArticleOfPrecedentMonth = $this->mouvementStockRepository->countTotalEntryPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitRefArticleOfPrecedentMonth = $this->mouvementStockRepository->countTotalExitPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalRefArticleOfPrecedentMonth = $totalEntryRefArticleOfPrecedentMonth - $totalExitRefArticleOfPrecedentMonth;
            $totalEntryArticleOfPrecedentMonth = $this->mouvementStockRepository->countTotalEntryPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitArticleOfPrecedentMonth = $this->mouvementStockRepository->countTotalExitPriceArticle($precedentMonthFirst, $precedentMonthLast);
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
     */
    public function graphiqueReference(): Response
    {
        $fiabiliteRef = $this->fiabilityByReferenceRepository->findAll();
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
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $colisRepository = $entityManager->getRepository(Colis::class);

        $arrivalCountByDays = $dashboardService->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository) {
            return $arrivageRepository->countByDates($dateMin, $dateMax);
        });

        $colisCountByDay = $dashboardService->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($colisRepository) {
            return $colisRepository->countByDates($dateMin, $dateMax);
        });

        return new JsonResponse([
            'data' => $arrivalCountByDays,
            'subCounters' => $colisCountByDay,
            'subLabel' => 'Colis',
            'label' => 'Autres arrivages',
            'lastLabel' => 'Arrivages du jour'
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
     * @param DashboardService $dashboardService
     * @return Response
     * @throws Exception
     */
    public function getDailyPacksStatistics(DashboardService $dashboardService): Response {
        $packsCountByDays = $dashboardService->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($dashboardService) {
            $resCounter = $dashboardService->getDashboardCounter(
                ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES,
                true,
                [
                    'minDate' => $dateMin,
                    'maxDate' => $dateMax
                ]
            );
            return !empty($resCounter['count']) ? $resCounter['count'] : 0;
        });

        return new JsonResponse($packsCountByDays);
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
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $colisRepository = $entityManager->getRepository(Colis::class);

        $arrivalsCountByWeek = $dashboardService->getWeeklyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository) {
            return $arrivageRepository->countByDates($dateMin, $dateMax);
        });

        $colisCountByWeek = $dashboardService->getWeeklyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($colisRepository) {
            return $colisRepository->countByDates($dateMin, $dateMax);
        });

        return new JsonResponse([
            'data' => $arrivalsCountByWeek,
            'subCounters' => $colisCountByWeek,
            'subLabel' => 'Colis',
            'label' => 'Autres arrivages',
            'lastLabel' => 'Arrivages de la semaine'
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
     * @param DashboardService $dashboardService
     * @param EntityManagerInterface $entityManager
     * @param EnCoursService $enCoursService
     * @param int $graph
     *
     * @return Response
     *
     * @throws NonUniqueResultException
     * @throws DBALException
     */
    public function getEnCoursCountByNatureAndTimespan(DashboardService $dashboardService,
                                                       EntityManagerInterface $entityManager,
                                                       EnCoursService $enCoursService,
                                                       int $graph): Response
	{

	    $adminDelay = '48:00';

		$natureRepository = $entityManager->getRepository(Nature::class);
		$emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $colisRepository = $entityManager->getRepository(Colis::class);

        $natureLabelToLookFor = $graph === 1 ? ParametrageGlobal::DASHBOARD_NATURE_COLIS : ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS;
        $empLabelToLookFor = $graph === 1 ? ParametrageGlobal::DASHBOARD_LOCATIONS_1 : ParametrageGlobal::DASHBOARD_LOCATIONS_2;

        // on récupère les natures paramétrées
        $paramNatureForGraph = $parametrageGlobalRepository->findOneByLabel($natureLabelToLookFor)->getValue();
        $naturesIdForGraph = !empty($paramNatureForGraph) ? explode(',', $paramNatureForGraph) : [];
        $naturesForGraph = !empty($naturesIdForGraph)
            ? $natureRepository->findBy(['id' => $naturesIdForGraph])
            : [];

        // on récupère les emplacements paramétrés
        $paramEmplacementWanted = $parametrageGlobalRepository->findOneByLabel($empLabelToLookFor)->getValue();
        $emplacementsIdWanted = !empty($paramEmplacementWanted) ? explode(',', $paramEmplacementWanted) : [];
        $emplacementsWanted = !empty($emplacementsIdWanted)
            ? $emplacementRepository->findBy(['id' => $emplacementsIdWanted])
            : [];

        $locationCounters = [];

        $olderPackLocation = [
            'locationLabel' => null,
            'locationId' => null,
            'packDateTime' => null
        ];

        if (!empty($naturesForGraph) && !empty($emplacementsWanted)) {
            $packsOnCluster = $colisRepository->getPackIntelOnLocations($emplacementsWanted, $naturesForGraph);

            $countByNatureBase = [];
            foreach ($naturesForGraph as $wantedNature) {
                $countByNatureBase[$wantedNature->getLabel()] = 0;
            }

            $graphData = $dashboardService->getObjectForTimeSpan(function (int $beginSpan, int $endSpan)
                                                                 use ($enCoursService, $countByNatureBase, $naturesForGraph, &$packsOnCluster, $adminDelay, &$locationCounters, &$olderPackLocation) {
                $countByNature = array_merge($countByNatureBase);
                $packUntreated = [];
                foreach ($packsOnCluster as $pack) {
                    $date = $enCoursService->getTrackingMovementAge($pack['firstTrackingDateTime']);
                    $timeInformation = $enCoursService->getTimeInformation($date, $adminDelay);
                    $countDownHours = isset($timeInformation['countDownLateTimespan'])
                        ? ($timeInformation['countDownLateTimespan'] / 1000 / 60 / 60)
                        : null;

                    if (isset($countDownHours)
                        && (
                            ($countDownHours < 0 && $beginSpan === -1) // count colis en retard
                            || ($countDownHours >= 0 && $countDownHours >= $beginSpan && $countDownHours < $endSpan)
                        )) {

                        $countByNature[$pack['natureLabel']]++;

                        $currentLocationLabel = $pack['currentLocationLabel'];
                        $currentLocationId = $pack['currentLocationId'];
                        $lastTrackingDateTime = $pack['lastTrackingDateTime'];

                        // get older pack
                        if ((
                                empty($olderPackLocation['locationLabel'])
                                || empty($olderPackLocation['locationId'])
                                || empty($olderPackLocation['packDateTime'])
                            )
                            || ($olderPackLocation['packDateTime'] > $lastTrackingDateTime)){
                            $olderPackLocation['locationLabel'] = $currentLocationLabel;
                            $olderPackLocation['locationId'] = $currentLocationId;
                            $olderPackLocation['packDateTime'] = $lastTrackingDateTime;
                        }

                        // increment counters
                        if (empty($locationCounters[$currentLocationId])) {
                            $locationCounters[$currentLocationId] = 0;
                        }

                        $locationCounters[$currentLocationId]++;
                    }
                    else {
                        $packUntreated[] = $pack;
                    }
                }
                $packsOnCluster = $packUntreated;
                return $countByNature;
            });
        }

        if (!isset($graphData)) {
            $graphData = $dashboardService->getObjectForTimeSpan(function () { return 0; });
        }

        $totalToDisplay = !empty($olderPackLocation['locationId'])
            ? ($locationCounters[$olderPackLocation['locationId']] ?? null)
            : null;

        $locationToDisplay = !empty($olderPackLocation['locationLabel'])
            ? $olderPackLocation['locationLabel']
            : null;

        return new JsonResponse([
            "data" => $graphData,
            'total' =>  isset($totalToDisplay) ? $totalToDisplay : '-',
            'location' =>  isset($locationToDisplay) ? $locationToDisplay : '-',
			'chartColors' => array_reduce(
			    $naturesForGraph,
                function (array $carry, Nature $nature) {
                    $color = $nature->getColor();
                    if (!empty($color)) {
                        $carry[$nature->getLabel()] = $color;
                    }
                    return $carry;
                },
                []),
        ]);
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
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getIndicatorsAdminReception(DashboardService $dashboardService): Response {
        $response = $dashboardService->getDataForReceptionAdminDashboard();
        return new JsonResponse($response);
    }

    /**
     * @Route(
     *     "/statistiques/reception-quai",
     *     name="get_indicators_reception_dock",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param DashboardService $dashboardService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getIndicatorsDockReception(DashboardService $dashboardService): Response
    {
        $response = $dashboardService->getDataForReceptionDockDashboard();
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
     * @return Response
     */
	public function getAssoRecepStatistics(Request $request): Response
	{
        $query = $request->query;
        $data = $this->dashboardService->getWeekAssoc(
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
     * @return Response
     */
	public function getArrivalUmStatistics(Request $request): Response
	{
        $query = $request->query;
        $data = $this->dashboardService->getWeekArrival(
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
	public function getDailyCarriersStatistics(DashboardService $dashboardService): Response {
        $carriersLabels = $dashboardService->getDailyArrivalCarriers();
        return new JsonResponse($carriersLabels);
	}
}
