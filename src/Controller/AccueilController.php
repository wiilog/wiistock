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
     * @var AlerteRepository
     */
    private $alerteRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    public function __construct(AlerteRepository $alerteRepository, EmplacementRepository $emplacementRepository)
    {
        $this->alerteRepository = $alerteRepository;
        $this->emplacementRepository = $emplacementRepository;
    }

    /**
     * @Route("/", name="accueil", methods={"GET"})
     */
    public function index(): Response
    {  
        $nbAlerte = $this->alerteRepository->countAlertes();

        return $this->render('accueil/index.html.twig', [
            'nbAlerte' => $nbAlerte,
            'emplacements'=> $this->emplacementRepository->findAll(),
        ]);
    }
}
