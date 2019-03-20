<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\DemandeRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;
use App\Repository\UtilisateurRepository;



use App\Repository\AlerteRepository;

/**
 * @Route("/accueil")
 */

class AccueilController extends AbstractController
{

     /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var LignreArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    public function __construct(LigneArticleRepository $ligneArticleRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->demandeRepository = $demandeRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
    }

    /**
     * @Route("/", name="accueil", methods={"GET"})
     */
    public function index(AlerteRepository $arlerteRepository, Request $request): Response
    {  
        $nbAlerte = $arlerteRepository->countAlertes();

        return $this->render('accueil/index.html.twig', [
            'nbAlerte' => $nbAlerte,
            'emplacements'=> $this->emplacementRepository->findAll(),
        ]);
    }
}
