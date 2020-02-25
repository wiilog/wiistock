<?php

namespace App\Controller;

use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Manutention;
use App\Entity\MouvementStock;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Repository\ArrivageRepository;
use App\Repository\ColisRepository;
use App\Repository\ParametrageGlobalRepository;
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
use App\Repository\StatutRepository;
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
     * @var StatutRepository
     */
    private $statutRepository;

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
                                StatutRepository $statutRepository,
                                CollecteRepository $collecteRepository,
                                EmplacementRepository $emplacementRepository,
                                MouvementStockRepository $mouvementStockRepository,
                                FiabilityByReferenceRepository $fiabilityByReferenceRepository)
    {
        $this->dashboardService = $dashboardService;
        $this->emplacementRepository = $emplacementRepository;
        $this->collecteRepository = $collecteRepository;
        $this->statutRepository = $statutRepository;
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
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function index(): Response
    {
        $data = $this->getDashboardData();
        return $this->render('accueil/index.html.twig', $data);
    }

	/**
	 * @Route("/statistiques/dashboard-externe/{page}", name="dashboard_ext", methods={"GET"})
	 * @param int $page
	 * @return Response
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function dasbhoardExt(?int $page = 1): Response
    {
        $data = $this->getDashboardData();
		$data['page'] = $page;
        return $this->render('accueil/dashboardExt.html.twig', $data);
    }

    /**
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function getDashboardData()
    {
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

        $statutCollecte = $this->statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER);
        $nbrDemandeCollecte = $this->collecteRepository->countByStatut($statutCollecte);

        $statutDemandeAT = $this->statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $nbrDemandeLivraisonAT = $this->demandeRepository->countByStatut($statutDemandeAT);

        $listStatutDemandeP = $this->statutRepository->getIdByCategorieNameAndStatusesNames(Demande::CATEGORIE, [Demande::STATUT_PREPARE, Demande::STATUT_INCOMPLETE]);
        $nbrDemandeLivraisonP = $this->demandeRepository->countByStatusesId($listStatutDemandeP);

        $statutManutAT = $this->statutRepository->findOneByCategorieNameAndStatutCode(Manutention::CATEGORIE, Manutention::STATUT_A_TRAITER);
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
                'DLtoTreat' => $this->statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_A_TRAITER),
                'DLincomplete' => $this->statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_INCOMPLETE),
                'DLprepared' => $this->statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_PREPARE),
                'DCToTreat' => $this->statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_A_TRAITER),
                'MToTreat' => $this->statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::MANUTENTION, Manutention::STATUT_A_TRAITER)
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
     * @param ArrivageRepository $arrivageRepository
     * @return Response
     * @throws Exception
     */
    public function getDailyArrivalsStatistics(DashboardService $dashboardService,
                                               ArrivageRepository $arrivageRepository): Response
    {

        $arrivalCountByDays = $dashboardService->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository) {
            return $arrivageRepository->countByDates($dateMin, $dateMax);
        });

        return new JsonResponse($arrivalCountByDays);
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
     * @param ColisRepository $colisRepository
     * @return Response
     * @throws Exception
     */
    public function getDailyPacksStatistics(DashboardService $dashboardService,
                                            ColisRepository $colisRepository): Response
    {

        $packsCountByDays = $dashboardService->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($colisRepository) {
            return $colisRepository->countByDates($dateMin, $dateMax);
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
     * @param ArrivageRepository $arrivageRepository
     * @return Response
     * @throws Exception
     */
    public function getWeeklyArrivalsStatistics(DashboardService $dashboardService,
                                                ArrivageRepository $arrivageRepository): Response
    {

        $arrivalsCountByWeek = $dashboardService->getWeeklyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository) {
            return $arrivageRepository->countByDates($dateMin, $dateMax);
        });

        return new JsonResponse($arrivalsCountByWeek);
    }

	/**
	 * @Route(
	 *     "/statistiques/encours-par-duree-et-nature/{graph}",
	 *     name="get_encours_count_by_nature_and_timespan",
	 *     options={"expose"=true},
	 *     methods="GET",
	 *     condition="request.isXmlHttpRequest()"
	 * )
	 * @param DashboardService $dashboardService
	 * @param ParametrageGlobalRepository $parametrageGlobalRepository
	 * @param EntityManagerInterface $entityManager
	 * @param EnCoursService $enCoursService
	 * @param EmplacementRepository $emplacementRepository
	 * @param int $graph
	 * @return Response
	 * @throws DBALException
	 * @throws NonUniqueResultException
	 */
    public function getEnCoursCountByNatureAndTimespan(DashboardService $dashboardService,
                                                       ParametrageGlobalRepository $parametrageGlobalRepository,
                                                       EntityManagerInterface $entityManager,
                                                       EnCoursService $enCoursService,
                                                       EmplacementRepository $emplacementRepository,
                                                       int $graph): Response
	{

		$natureRepository = $entityManager->getRepository(Nature::class);

        $natureLabelToLookFor = $graph === 1 ? ParametrageGlobal::DASHBOARD_NATURE_COLIS : ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS;
        $empLabelToLookFor = $graph === 1 ? ParametrageGlobal::DASHBOARD_LOCATIONS_1 : ParametrageGlobal::DASHBOARD_LOCATIONS_2;

        $paramNatureForGraph = $parametrageGlobalRepository->findOneByLabel($natureLabelToLookFor)->getValue();
        $naturesForGraph = !empty($paramNatureForGraph) ? explode(',', $paramNatureForGraph) : [];
        $naturesForGraph = array_map(function ($natureId) use ($natureRepository) {
            return $natureRepository->find($natureId);
        }, $naturesForGraph);

        $colorsNatures = [];
        foreach ($naturesForGraph as $natureForGraph) {
        	$colorsNatures[$natureForGraph->getLabel()] = $natureForGraph->getColor();
		}

        $paramEmplacementWanted = $parametrageGlobalRepository->findOneByLabel($empLabelToLookFor)->getValue();
        $emplacementsWanted = !empty($paramEmplacementWanted) ? explode(',', $paramEmplacementWanted) : [];
        $emplacementsWanted = array_map(function ($emplacementId) use ($emplacementRepository) {
            return $emplacementRepository->find($emplacementId);
        }, $emplacementsWanted);
        $highestTotal = -1;
        $enCoursToMonitor = null;
        $empToKeep = null;
        foreach ($emplacementsWanted as $emplacementWanted) {
            $enCoursOnThisEmp = $enCoursService->getEnCoursForEmplacement($emplacementWanted);
            $enCoursCountForTimeSpanAndNature = $dashboardService->getObjectForTimeSpan(function (int $beginSpan, int $endSpan) use (
                $enCoursService,
                $naturesForGraph,
                $enCoursOnThisEmp
            ) {
                return $enCoursService->getCountByNatureForEnCoursForTimeSpan($enCoursOnThisEmp['data'], $beginSpan, $endSpan, $naturesForGraph);
            });
            $total = 0;
            foreach ($enCoursCountForTimeSpanAndNature as $timeSpan => $natures) {
                foreach ($natures as $nature => $count) {
                    if ($timeSpan !== "Retard") $total += $count;
                }
            }
            if ($total >= $highestTotal) {
                $enCoursToMonitor = $enCoursCountForTimeSpanAndNature;
                $highestTotal = $total;
                $empToKeep = $emplacementWanted;
            }
        }
        if ($highestTotal === -1) {
            $enCoursToMonitor = $dashboardService->getObjectForTimeSpan(function () {
                return 0;
            });
        }

        return new JsonResponse([
            "data" => $enCoursToMonitor,
            'total' => $highestTotal === -1 ? '-' : $highestTotal,
            "location" => $empToKeep && $highestTotal > -1 ? $empToKeep->getLabel() : '-',
			'colorsNatures' => $colorsNatures,
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
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
	public function getDailyCarriersStatistics(DashboardService $dashboardService): Response {
        $carriersLabels = $dashboardService->getDailyArrivalCarriers();
        return new JsonResponse($carriersLabels);
	}
}
