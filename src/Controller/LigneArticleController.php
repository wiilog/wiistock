<?php
//
//namespace App\Controller;
//
//use App\Entity\Action;
//use App\Entity\Demande;
//use App\Entity\LigneArticle;
//
//use App\Entity\Menu;
//use App\Form\LivraisonType;
//
//use App\Repository\DemandeRepository;
//use App\Repository\ReferenceArticleRepository;
//use App\Repository\LigneArticleRepository;
//use App\Repository\StatutRepository;
//use App\Repository\EmplacementRepository;
//use App\Repository\UtilisateurRepository;
//
//use App\Service\UserService;
//use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
//use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
//use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\HttpFoundation\Response;
//use Symfony\Component\Routing\Annotation\Route;
//
///**
// * @Route("/ligne-article")
// */
//class LigneArticleController extends AbstractController
//{
//    /**
//     * @var StatutRepository
//     */
//    private $statutRepository;
//
//    /**
//     * @var LignreArticleRepository
//     */
//    private $ligneArticleRepository;
//
//    /**
//     * @var EmplacementRepository
//     */
//    private $emplacementRepository;
//
//    /**
//     * @var UtilisateurRepository
//     */
//    private $utilisateurRepository;
//
//    /**
//     * @var DemandeRepository
//     */
//    private $demandeRepository;
//
//    /**
//     * @var ReferenceArticleRepository
//     */
//    private $referenceArticleRepository;
//
//    /**
//     * @var UserService
//     */
//    private $userService;
//
//
//    public function __construct(LigneArticleRepository $ligneArticleRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, UserService $userService)
//    {
//        $this->statutRepository = $statutRepository;
//        $this->emplacementRepository = $emplacementRepository;
//        $this->demandeRepository = $demandeRepository;
//        $this->utilisateurRepository = $utilisateurRepository;
//        $this->referenceArticleRepository = $referenceArticleRepository;
//        $this->ligneArticleRepository = $ligneArticleRepository;
//        $this->userService = $userService;
//    }
//
//
//
//
//
//
//}
