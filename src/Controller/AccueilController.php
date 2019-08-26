<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\SeuilAlerteService;

use App\Repository\EmplacementRepository;
use App\Repository\AlerteRepository;
use App\Repository\CollecteRepository;
use App\Repository\StatutRepository;
use App\Repository\DemandeRepository;
use App\Repository\ServiceRepository;

use App\Entity\Collecte;
use App\Entity\Livraison;
use App\Entity\Demande;
use App\Entity\Service;

/**
 * @Route("/accueil")
 */
class AccueilController extends AbstractController
{
    /**
     * @var AlerteRepository
     */
    private $alerteRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var SeuilAlerteServic
     */
    private $seuilAlerteService;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var ServiceRepository
     */
    private $serviceRepository;

    public function __construct(ServiceRepository $serviceRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, CollecteRepository $collecteRepository, SeuilAlerteService $seuilAlerteService, AlerteRepository $alerteRepository, EmplacementRepository $emplacementRepository)
    {
        $this->alerteRepository = $alerteRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->seuilAlerteService = $seuilAlerteService;
        $this->collecteRepository = $collecteRepository;
        $this->statutRepository = $statutRepository;
        $this->demandeRepository = $demandeRepository;
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * @Route("/", name="accueil", methods={"GET"})
     */
    public function index(): Response
    {
    	$nbAlerts = $this->alerteRepository->countByLimitReached();

        $statutCollecte = $this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_A_TRAITER);
        $nbrDemandeCollecte = $this->collecteRepository->countByStatut($statutCollecte);

        $statutDemandeAT = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $nbrDemandeLivraisonAT = $this->demandeRepository->countByStatut($statutDemandeAT);

        $statutDemandeP = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_PREPARE);
        $nbrDemandeLivraisonP = $this->demandeRepository->countByStatut($statutDemandeP);

        $statutServiceAT = $this->statutRepository->findOneByCategorieAndStatut(Service::CATEGORIE, Service::STATUT_A_TRAITER);
        $nbrDemandeManutentionAT = $this->serviceRepository->countByStatut($statutServiceAT);

        return $this->render('accueil/index.html.twig', [
            'nbAlerts' => $nbAlerts,
            'nbDemandeCollecte' => $nbrDemandeCollecte,
            'nbDemandeLivraisonAT' => $nbrDemandeLivraisonAT,
            'nbDemandeLivraisonP' => $nbrDemandeLivraisonP,
            'nbDemandeManutentionAT' => $nbrDemandeManutentionAT,
            'emplacements' => $this->emplacementRepository->findAll(),
        ]);
    }
}
