<?php

namespace App\Controller;

use App\Entity\MouvementStock;
use App\Repository\AlerteExpiryRepository;
use App\Repository\ArticleRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\ReferenceArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\EmplacementRepository;
use App\Repository\AlerteStockRepository;
use App\Repository\CollecteRepository;
use App\Repository\StatutRepository;
use App\Repository\DemandeRepository;
use App\Repository\ManutentionRepository;

use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Manutention;

/**
 * @Route("/accueil")
 */
class AccueilController extends AbstractController
{
    /**
     * @var AlerteStockRepository
     */
    private $alerteStockRepository;

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

    public function __construct(ArticleRepository $articleRepository, ReferenceArticleRepository $referenceArticleRepository, AlerteExpiryRepository $alerteExpiryRepository, ManutentionRepository $manutentionRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, CollecteRepository $collecteRepository, AlerteStockRepository $alerteStockRepository, EmplacementRepository $emplacementRepository, MouvementStockRepository $mouvementStockRepository)
    {
        $this->alerteStockRepository = $alerteStockRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->collecteRepository = $collecteRepository;
        $this->statutRepository = $statutRepository;
        $this->demandeRepository = $demandeRepository;
        $this->manutentionRepository = $manutentionRepository;
        $this->alerteExpiryRepository = $alerteExpiryRepository;
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->refArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
    }

    /**
     * @Route("/", name="accueil", methods={"GET"})
     */
    public function index(): Response
    {
    	$nbAlertsSecurity = $this->alerteStockRepository->countAlertsSecurityActive();
    	$nbAlerts = $this->alerteStockRepository->countAlertsWarningActive();
    	$nbAlertsExpiry = $this->alerteExpiryRepository->countAlertsExpiryActive()
			+ $this->alerteExpiryRepository->countAlertsExpiryGeneralActive();
    	$types = [
    	    MouvementStock::TYPE_INVENTAIRE_ENTREE,
            MouvementStock::TYPE_INVENTAIRE_SORTIE
        ];
        $nbStockInventoryMouvements = $this->mouvementStockRepository->countByTypes($types);
    	$nbActiveRefAndArt = $this->refArticleRepository->countActiveTypeRefRef() + $this->articleRepository->countActiveArticles();
        $nbrFiabiliteReference = (1 - ($nbStockInventoryMouvements / $nbActiveRefAndArt)) * 100;

        $totalRefArticle = $this->mouvementStockRepository->countTotalPriceRefArticle();
        $totalArticle = $this->mouvementStockRepository->countTotalPriceArticle();
        $nbrFiabiliteMonetaire = $totalRefArticle + $totalArticle;

        $statutCollecte = $this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_A_TRAITER);
        $nbrDemandeCollecte = $this->collecteRepository->countByStatut($statutCollecte);

        $statutDemandeAT = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $nbrDemandeLivraisonAT = $this->demandeRepository->countByStatut($statutDemandeAT);

        $statutDemandeP = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_PREPARE);
        $nbrDemandeLivraisonP = $this->demandeRepository->countByStatut($statutDemandeP);

        $statutManutAT = $this->statutRepository->findOneByCategorieAndStatut(Manutention::CATEGORIE, Manutention::STATUT_A_TRAITER);
        $nbrDemandeManutentionAT = $this->manutentionRepository->countByStatut($statutManutAT);

        return $this->render('accueil/index.html.twig', [
            'nbAlerts' => $nbAlerts,
            'nbAlertsSecurity' => $nbAlertsSecurity,
            'nbAlertsExpiry' => $nbAlertsExpiry,
            'nbDemandeCollecte' => $nbrDemandeCollecte,
            'nbDemandeLivraisonAT' => $nbrDemandeLivraisonAT,
            'nbDemandeLivraisonP' => $nbrDemandeLivraisonP,
            'nbDemandeManutentionAT' => $nbrDemandeManutentionAT,
            'emplacements' => $this->emplacementRepository->findAll(),
            'nbrFiabiliteReference' => $nbrFiabiliteReference,
            'nbrFiabiliteMonetaire' => $nbrFiabiliteMonetaire,
        ]);
    }
}
