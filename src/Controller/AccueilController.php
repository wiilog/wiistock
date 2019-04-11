<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\SeuilAlerteService;



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
     * @var AlerteRepository
     */
    private $alerteRepository;

     /**
     * @var SeuilAlerteServic
     */
    private $seuilAlerteService;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    public function __construct(SeuilAlerteService $seuilAlerteService,AlerteRepository $alerteRepository, EmplacementRepository $emplacementRepository)
    {
        $this->alerteRepository = $alerteRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->seuilAlerteService = $seuilAlerteService;
    }

    /**
     * @Route("/", name="accueil", methods={"GET"})
     */
    public function index(): Response
    {  
        $nbAlerte = $this->seuilAlerteService->thresholdReaches();

        return $this->render('accueil/index.html.twig', [
            'nbAlerte' => $nbAlerte,
            'emplacements'=> $this->emplacementRepository->findAll(),
        ]);
    }
}
