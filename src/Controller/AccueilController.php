<?php

namespace App\Controller;

use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Manutention;
use App\Entity\MouvementStock;

use App\Entity\ParametrageGlobal;
use App\Repository\ArrivageRepository;
use App\Repository\ColisRepository;
use App\Repository\NatureRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\UrgenceRepository;
use App\Service\DashboardService;

use App\Service\EnCoursService;
use App\Service\StatisticsService;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
 * @Route("/accueil")
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

    public function __construct(DashboardService $dashboardService, ArticleRepository $articleRepository, ReferenceArticleRepository $referenceArticleRepository, AlerteExpiryRepository $alerteExpiryRepository, ManutentionRepository $manutentionRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, CollecteRepository $collecteRepository, EmplacementRepository $emplacementRepository, MouvementStockRepository $mouvementStockRepository, FiabilityByReferenceRepository $fiabilityByReferenceRepository)
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
     * @Route("/", name="accueil", methods={"GET"})
     */
    public function index(): Response
    {
        $data = $this->getDashboardData();
        return $this->render('accueil/index.html.twig', $data);
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

        $statutCollecte = $this->statutRepository->findOneByCategorieNameAndStatutName(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER);
        $nbrDemandeCollecte = $this->collecteRepository->countByStatut($statutCollecte);

        $statutDemandeAT = $this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $nbrDemandeLivraisonAT = $this->demandeRepository->countByStatut($statutDemandeAT);

        $listStatutDemandeP = $this->statutRepository->getIdByCategorieNameAndStatusesNames(Demande::CATEGORIE, [Demande::STATUT_PREPARE, Demande::STATUT_INCOMPLETE]);
        $nbrDemandeLivraisonP = $this->demandeRepository->countByStatusesId($listStatutDemandeP);

        $statutManutAT = $this->statutRepository->findOneByCategorieNameAndStatutName(Manutention::CATEGORIE, Manutention::STATUT_A_TRAITER);
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
            'lastDayOfWeek' => date("d/m/Y", strtotime('sunday this week')),
			'indicatorsReceptionDock' => $this->dashboardService->getDataForReceptionDashboard()
        ];
    }

    /**
     * @Route("/statistiques-fiabilite-monetaire", name="get_monetary_fiability_statistics", options={"expose"=true}, methods="GET|POST")
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
     * @Route("/graphique-reference", name="graph_ref", options={"expose"=true}, methods="GET|POST")
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
//
//    /**
//     * @Route("/tableau-de-bord", name="get_dashboard", options={"expose"=true}, methods="GET|POST")
//     * @return Response
//     * @throws NoResultException
//     * @throws NonUniqueResultException
//     */
//    public function getDashboard(): Response
//    {
//        $data = $this->getDashboardData();
//        $html = $this->renderView('accueil/dashboardLinks.html.twig', $data);
//        return new JsonResponse($html);
//    }

    /**
     * @Route("/statistiques-arrivages-jour", name="get_daily_arrivals_statistics", options={"expose"=true}, methods="GET")
     * @param StatisticsService $statisticsService
     * @param ArrivageRepository $arrivageRepository
     * @return Response
     * @throws Exception
     */
    public function getDailyArrivalsStatistics(StatisticsService $statisticsService,
                                               ArrivageRepository $arrivageRepository): Response
    {

        $arrivalCountByDays = $statisticsService->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository) {
            return $arrivageRepository->countByDates($dateMin, $dateMax);
        });

        return new JsonResponse($arrivalCountByDays);
    }

    /**
     * @Route("/statistiques-colis-jour", name="get_daily_packs_statistics", options={"expose"=true}, methods="GET")
     * @param StatisticsService $statisticsService
     * @param ColisRepository $colisRepository
     * @return Response
     * @throws Exception
     */
    public function getDailyPacksStatistics(StatisticsService $statisticsService,
                                            ColisRepository $colisRepository): Response
    {

        $packsCountByDays = $statisticsService->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($colisRepository) {
            return $colisRepository->countByDates($dateMin, $dateMax);
        });

        return new JsonResponse($packsCountByDays);
    }

    /**
     * @Route("/statistiques-arrivages-semaine", name="get_weekly_arrivals_statistics", options={"expose"=true}, methods="GET")
     * @param StatisticsService $statisticsService
     * @param ArrivageRepository $arrivageRepository
     * @return Response
     * @throws Exception
     */
    public function getWeeklyArrivalsStatistics(StatisticsService $statisticsService,
                                                ArrivageRepository $arrivageRepository): Response
    {

        $arrivalsCountByWeek = $statisticsService->getWeeklyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository) {
            return $arrivageRepository->countByDates($dateMin, $dateMax);
        });

        return new JsonResponse($arrivalsCountByWeek);
    }

    /**
     * @Route("/statistiques-encours-par-duree-et-nature/{graph}", name="get_encours_count_by_nature_and_timespan", options={"expose"=true}, methods="GET")
     * @param StatisticsService $statisticsService
     * @param ParametrageGlobalRepository $parametrageGlobalRepository
     * @param EnCoursService $enCoursService
     * @param NatureRepository $natureRepository
     * @param EmplacementRepository $emplacementRepository
     * @param int $graph
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getEnCoursCountByNatureAndTimespan(
        StatisticsService $statisticsService,
        ParametrageGlobalRepository $parametrageGlobalRepository,
        EnCoursService $enCoursService,
        NatureRepository $natureRepository,
        EmplacementRepository $emplacementRepository,
        int $graph): Response
    {
        $natureLabelToLookFor = $graph === 1 ? ParametrageGlobal::DASHBOARD_NATURE_COLIS : ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS;
        $empLabelToLookFor = $graph === 1 ? ParametrageGlobal::DASHBOARD_LOCATIONS_1 : ParametrageGlobal::DASHBOARD_LOCATIONS_2;
        $naturesForGraph = explode(',', $parametrageGlobalRepository->findOneByLabel($natureLabelToLookFor)->getValue());
        $naturesForGraph = array_map(function (int $natureId) use ($natureRepository) {
            return $natureRepository->find($natureId);
        }, $naturesForGraph);
        $emplacementsWanted = explode(',', $parametrageGlobalRepository->findOneByLabel($empLabelToLookFor)->getValue());
        $emplacementsWanted = array_map(function (int $emplacementId) use ($emplacementRepository) {
            return $emplacementRepository->find($emplacementId);
        }, $emplacementsWanted);
        $highestTotal = -1;
        $enCoursToMonitor = null;
        $empToKeep = null;
        foreach ($emplacementsWanted as $emplacementWanted) {
            $enCoursOnThisEmp = $enCoursService->getEnCoursForEmplacement($emplacementWanted);
            $enCoursCountForTimeSpanAndNature = $statisticsService->getObjectForTimeSpan(function (int $beginSpan, int $endSpan) use (
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
        return new JsonResponse([
            "data" => $enCoursToMonitor,
            'total' => $highestTotal,
            "location" => $empToKeep->getLabel()
        ]);
    }

    /**
     * @Route("/statistiques-urgences-et-encours-admin", name="get_encours_and_emergencies_admin", options={"expose"=true}, methods="GET")
     * @param EmplacementRepository $emplacementRepository
     * @param UrgenceRepository $urgenceRepository
     * @param EnCoursService $enCoursService
     * @param ParametrageGlobalRepository $parametrageGlobalRepository
     * @return Response
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getEmergenciesAndAndEnCoursForAdmin(
        EmplacementRepository $emplacementRepository,
        UrgenceRepository $urgenceRepository,
        EnCoursService $enCoursService,
        ParametrageGlobalRepository $parametrageGlobalRepository): Response
    {
        $empIdForLitige =
            $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_LITIGES)
                ?
                $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_LITIGES)->getValue()
                :
                null;
        $empIdForUrgence =
            $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_URGENCES)
                ?
                $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_URGENCES)->getValue()
                :
                null;
        $empIdForClearance =
            $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN)
                ?
                $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN)->getValue()
                :
                null;
        $empForLitige = $empIdForLitige ? $emplacementRepository->find($empIdForLitige) : null;
        $empForUrgence = $empIdForUrgence ? $emplacementRepository->find($empIdForUrgence) : null;
        $empForClearance = $empIdForClearance ? $emplacementRepository->find($empIdForClearance) : null;
        $response = [
            'enCoursLitige' => $empForLitige ? [
                'count' => count($enCoursService->getEnCoursForEmplacement($empForLitige)['data']),
                'label' => $empForLitige->getLabel()
            ] : null,
            'enCoursClearance' => $empForClearance ? [
                'count' => count($enCoursService->getEnCoursForEmplacement($empForClearance)['data']),
                'label' => $empForClearance->getLabel()
            ] : null,
            'enCoursUrgence' => $empForUrgence ? [
                'count' => count($enCoursService->getEnCoursForEmplacement($empForUrgence)['data']),
                'label' => $empForUrgence->getLabel()
            ] : null,
            'urgenceCount' => $urgenceRepository->countUnsolved(),
        ];
        return new JsonResponse($response);
    }

    /**
     * @Route("/statistiques-receoption-quai", name="get_indicators_reception_dock", options={"expose"=true}, methods="GET")
     */
    public function getIndicatorsDockReception(): Response
    {
    	$response = $this->dashboardService->getDataForReceptionDashboard();

        return new JsonResponse($response);
    }

	/**
	 * @Route("/statistiques-receptions-associations", name="get_asso_recep_statistics", options={"expose"=true}, methods={"GET|POST"})
	 */
	public function getAssoRecepStatistics(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			$post = $request->request;
			return new JsonResponse($this->dashboardService->getWeekAssoc($post->get('firstDay'), $post->get('lastDay'), $post->get('beforeAfter')));
		}
		throw new NotFoundHttpException("404");
	}

	/**
	 * @Route("/statistiques-arrivages-um", name="get_arrival_um_statistics", options={"expose"=true},methods={"GET|POST"})
	 */
	public function getArrivalUmStatistics(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			$post = $request->request;
			return new JsonResponse($this->dashboardService->getWeekArrival($post->get('firstDay'), $post->get('lastDay'), $post->get('beforeAfter')));
		}
		throw new NotFoundHttpException("404");
	}
}
