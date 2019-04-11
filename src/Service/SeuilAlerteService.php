<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;


use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;

use App\Service\RefArticleDataService;

use App\Repository\ArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\FilterRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\AlerteRepository;


use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;



class SeuilAlerteService
{
    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /*
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /*
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var FilterRepository
     */
    private $filterRepository;

    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;
    
    /**
     * @var AlerteRepository
     */
    private $alerteRepository;

    /**
     * @var object|string
     */
    private $user;

    private $em;


    public function __construct(AlerteRepository $alerteRepository ,RefArticleDataService $refArticleDataService, ArticleRepository $articleRepository, ArticleFournisseurRepository $articleFournisseurRepository, TypeRepository  $typeRepository, StatutRepository $statutRepository, EntityManagerInterface $em, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, ChampsLibreRepository $champsLibreRepository, FilterRepository $filterRepository, \Twig_Environment $templating, TokenStorageInterface $tokenStorage)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->statutRepository = $statutRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->filterRepository = $filterRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->typeRepository = $typeRepository;
        $this->alerteRepository = $alerteRepository;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
    }

    /**
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function thresholdReaches()
    {
        $seuils = $this->alerteRepository->findAll();
        $nbSeuilAtteint = 0;
        foreach ($seuils as $seuil) {
            $quantiteAR = $this->referenceArticleRepository->getQuantiteStockById($seuil->getAlerteRefArticle()->getId());
            if ($seuil->getAlerteSeuil() > $quantiteAR) {
                $seuil->setSeuilAtteint(true);
                $nbSeuilAtteint++ ;
            } else {
                $seuil->getSeuilAtteint(false);
            }
            
        }
        $entityManager = $this->em;
        $entityManager->flush();
        
        return $nbSeuilAtteint;
    }
}