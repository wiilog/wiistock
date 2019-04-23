<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\Menu;
use App\Entity\OrdreCollecte;
use App\Repository\CollecteReferenceRepository;
use App\Repository\CollecteRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\StatutRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/ordre-collecte")
 */
class OrdreCollecteController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var OrdreCollecteRepository
     */
    private $ordreCollecteRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var CollecteReferenceRepository
     */
    private $collecteReferenceRepository;


    public function __construct(UserService $userService, OrdreCollecteRepository $ordreCollecteRepository, StatutRepository $statutRepository, CollecteRepository $collecteRepository, CollecteReferenceRepository $collecteReferenceRepository)
    {
        $this->userService = $userService;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->statutRepository = $statutRepository;
        $this->collecteRepository = $collecteRepository;
        $this->collecteReferenceRepository = $collecteReferenceRepository;
    }

    /**
     * @Route("/", name="ordre_collecte_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('ordre_collecte/index.html.twig');
    }

    /**
     * @Route("/api", name="ordre_collecte_api", options={"expose"=true})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest())
        {
            if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $collectes = $this->ordreCollecteRepository->findAll();
            $rows = [];
            foreach ($collectes as $collecte) {
                $url['show'] = $this->generateUrl('ordre_collecte_show', ['id' => $collecte->getId()]);
                $rows[] = [
                    'id' => ($collecte->getId() ? $collecte->getId() : ''),
                    'Numéro' => ($collecte->getNumero() ? $collecte->getNumero() : ''),
                    'Date' => ($collecte->getDate() ? $collecte->getDate()->format('d-m-Y') : ''),
                    'Statut' => ($collecte->getStatut() ? $collecte->getStatut()->getNom() : ''),
                    'Opérateur' => ($collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : ''),
                    'Actions' => $this->renderView('ordre_collecte/datatableCollecteRow.html.twig', ['url' => $url])
                ];
            }

            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="ordre_collecte_show", methods={"GET","POST"})
     */
    public function show(OrdreCollecte $ordreCollecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('ordre_collecte/show.html.twig', [
            'collecte' => $ordreCollecte,
//            'preparation' => $this->preparationRepository->find($ordreCollecte->getPreparation()->getId()),
            'finished' => ($ordreCollecte->getStatut()->getNom() === Collecte::STATUS_FIN)
        ]);
    }

    /**
     * @Route("/finir/{id}", name="ordre_collecte_finish", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function finish(OrdreCollecte $collecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::CREATE)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($collecte->getStatut()->getnom() ===  OrdreCollecte::STATUT_A_TRAITER) {

            $collecte
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
                ->setDate(new \DateTime('now'));

            $demande = $collecte->getDemandeCollecte();
            $statutTraite = $this->statutRepository->findOneByCategorieAndStatut(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE);
            $demande->setStatut($statutTraite);

            // on modifie la quanitité des articles de référence liés à la collecte
            $ligneArticles = $this->collecteReferenceRepository->getByCollecte($collecte->getDemandeCollecte());

            foreach ($ligneArticles as $ligneArticle) { /** @var  CollecteReference $ligneArticle */
                $refArticle = $ligneArticle->getReferenceArticle();
                dump($refArticle->getReference());
                dump($refArticle->getQuantiteStock());
                $refArticle->setQuantiteStock($refArticle->getQuantiteStock() + $ligneArticle->getQuantite());
            }

            // on modifie le statut des articles liés à la collecte
            $demandeCollecte = $collecte->getDemandeCollecte();

            $articles = $demandeCollecte->getArticles();
            foreach ($articles as $article) {
                $article->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_INACTIF));
            }
        }
        $this->getDoctrine()->getManager()->flush();
        return $this->redirectToRoute('ordre_collecte_show', [
            'id' => $collecte->getId()
        ]);
    }

    /**
     * @Route("/api-article/{id}", name="ordre_collecte_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function apiArticle(Request $request, OrdreCollecte $collecte): Response
    {
        if ($request->isXmlHttpRequest())
        {
            if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $collecte->getDemandeCollecte();

            if ($demande) {

                $ligneArticle = $this->collecteReferenceRepository->getByCollecte($demande->getId());

                $rows = [];
                foreach ($ligneArticle as $ligneArticle) { /** @var CollecteReference $ligneArticle */
                    $referenceArticle = $ligneArticle->getReferenceArticle();
                    $rows[] = [
                        "Référence CEA" => $referenceArticle ? $referenceArticle->getReference() : ' ',
                        "Libellé" => $referenceArticle ? $referenceArticle->getLibelle() : ' ',
                        "Quantité" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ' '),
                    ];
                }

                $data['data'] = $rows;
            } else {
                $data = false; //TODO gérer retour message erreur
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
