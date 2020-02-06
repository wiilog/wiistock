<?php

namespace App\Controller;

use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Manutention;
use App\Entity\MouvementStock;

use App\Service\DashboardService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    	$nbAlerts = $this->refArticleRepository->countAlert();
//    	$nbAlertsExpiry = $this->alerteExpiryRepository->countAlertsExpiryActive()
//			+ $this->alerteExpiryRepository->countAlertsExpiryGeneralActive();
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

        return $this->render('accueil/index.html.twig', [
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
				'DLprepared'=> $this->statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_PREPARE),
				'DCToTreat' => $this->statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_A_TRAITER),
				'MToTreat' => $this->statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::MANUTENTION, Manutention::STATUT_A_TRAITER)
			],
			'firstDayOfWeek' => date("d/m/Y", strtotime('monday this week')),
			'lastDayOfWeek' => date("d/m/Y", strtotime('sunday this week'))
		]);
    }

    /**
     * @Route("/graphique-monetaire", name="graph_monetaire", options={"expose"=true}, methods="GET|POST")
     */
    public function graphiqueMonetaire(): Response
    {
        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $lastDayOfCurrentMonth = date("Y-m-d", strtotime("last day of this month", strtotime($firstDayOfCurrentMonth)));
        $precedentMonthFirst = $firstDayOfCurrentMonth;
        $precedentMonthLast = $lastDayOfCurrentMonth;
        $idx = 0;
        $value = [];
        while ($idx !== 6 ) {
            $month = date("m", strtotime($precedentMonthFirst));
            $month = date("F", mktime(0,0,0, $month, 10));
            $totalEntryRefArticleOfPrecedentMonth = $this->mouvementStockRepository->countTotalEntryPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitRefArticleOfPrecedentMonth = $this->mouvementStockRepository->countTotalExitPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalRefArticleOfPrecedentMonth = $totalEntryRefArticleOfPrecedentMonth - $totalExitRefArticleOfPrecedentMonth;
            $totalEntryArticleOfPrecedentMonth = $this->mouvementStockRepository->countTotalEntryPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitArticleOfPrecedentMonth = $this->mouvementStockRepository->countTotalExitPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalArticleOfPrecedentMonth = $totalEntryArticleOfPrecedentMonth - $totalExitArticleOfPrecedentMonth;

            $nbrFiabiliteMonetaireOfPrecedentMonth = $totalRefArticleOfPrecedentMonth + $totalArticleOfPrecedentMonth;

            $value[] = [
                'mois' => $month,
                'nbr' => $nbrFiabiliteMonetaireOfPrecedentMonth];
            $precedentMonthFirst = date("Y-m-d", strtotime("-1 month", strtotime($precedentMonthFirst)));
            $precedentMonthLast = date("Y-m-d", strtotime("last day of -1 month", strtotime($precedentMonthLast)));
            $idx += 1;
        }

        $data = $value;
        return new JsonResponse($data);
    }

    /**
     * @Route("/graphique-reference", name="graph_ref", options={"expose"=true}, methods="GET|POST")
     */
    public function graphiqueReference(): Response
    {

        $fiabiliteRef = $this->fiabilityByReferenceRepository->findAll();
        $value[] = [];
        foreach ($fiabiliteRef as $reference)
        {
            $date = $reference->getDate();
            $indicateur = $reference->getIndicateur();
            $dateTimeTostr = $date->format('Y-m-d');
            $month = date("m", strtotime($dateTimeTostr));
            $month = date("F", mktime(0,0,0, $month, 10));
            $value[] = [
                'mois' => $month,
                'nbr' => $indicateur
            ];
        }
        $data = $value;
        return new JsonResponse($data);
    }
}
