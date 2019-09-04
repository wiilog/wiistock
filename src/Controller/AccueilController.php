<?php

namespace App\Controller;

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

    public function __construct(ManutentionRepository $manutentionRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, CollecteRepository $collecteRepository, AlerteStockRepository $alerteStockRepository, EmplacementRepository $emplacementRepository)
    {
        $this->alerteStockRepository = $alerteStockRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->collecteRepository = $collecteRepository;
        $this->statutRepository = $statutRepository;
        $this->demandeRepository = $demandeRepository;
        $this->manutentionRepository = $manutentionRepository;
    }

    /**
     * @Route("/", name="accueil", methods={"GET"})
     */
    public function index(): Response
    {
    	$nbAlertsSecurity = $this->alerteStockRepository->countActivatedLimitSecurityReached();
    	$nbAlerts = $this->alerteStockRepository->countActivatedLimitReached();

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
            'nbDemandeCollecte' => $nbrDemandeCollecte,
            'nbDemandeLivraisonAT' => $nbrDemandeLivraisonAT,
            'nbDemandeLivraisonP' => $nbrDemandeLivraisonP,
            'nbDemandeManutentionAT' => $nbrDemandeManutentionAT,
            'emplacements' => $this->emplacementRepository->findAll(),
        ]);
    }
}
