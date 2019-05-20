<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\Menu;
use App\Entity\OrdreCollecte;

use App\Repository\ArticleRepository;
use App\Repository\CollecteReferenceRepository;
use App\Repository\CollecteRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\StatutRepository;
use App\Repository\MailerServerRepository;

use App\Service\MailerService;
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

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var MailerServerRepository
     */
    private $mailerServerRepository;


    public function __construct(MailerServerRepository $mailerServerRepository, OrdreCollecteRepository $ordreCollecteRepository, StatutRepository $statutRepository, CollecteRepository $collecteRepository, CollecteReferenceRepository $collecteReferenceRepository, UserService $userService, MailerService $mailerService, ArticleRepository $articleRepository)
    {
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->statutRepository = $statutRepository;
        $this->collecteRepository = $collecteRepository;
        $this->collecteReferenceRepository = $collecteReferenceRepository;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->mailerServerRepository = $mailerServerRepository;
    }

    /**
     * @Route("/", name="ordre_collecte_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('ordre_collecte/index.html.twig');
    }

    /**
     * @Route("/api", name="ordre_collecte_api", options={"expose"=true})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
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
            'finished' => $ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_TRAITE
        ]);
    }

    /**
     * @Route("/finir/{id}", name="ordre_collecte_finish", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function finish(OrdreCollecte $collecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($collecte->getStatut()->getnom() ===  OrdreCollecte::STATUT_A_TRAITER) {

            // on modifie le statut de l'ordre de collecte
            $collecte
                ->setUtilisateur($this->getUser())
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
                ->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));

            // on modifie le statut de la demande de collecte
            $demandeCollecte = $collecte->getDemandeCollecte();
            $demandeCollecte->setStatut($this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_COLLECTE));

            if ($this->mailerServerRepository->findAll()) {
                $this->mailerService->sendMail(
                    'FOLLOW GT // Collecte effectuée',
                    $this->renderView(
                        'mails/mailCollecteDone.html.twig',
                        [
                            'collecte' => $demandeCollecte,
                            
                        ]
                    ),
                    $demandeCollecte->getDemandeur()->getEmail()
                );
            }

            // on modifie la quantité des articles de référence liés à la collecte
            $ligneArticles = $this->collecteReferenceRepository->getByCollecte($collecte->getDemandeCollecte());

            $addToStock = $demandeCollecte->getStockOrDestruct();

            // cas de mise en stockage
            if ($addToStock) {
                foreach ($ligneArticles as $ligneArticle) {
                    /** @var  CollecteReference $ligneArticle */
                    $refArticle = $ligneArticle->getReferenceArticle();
                    $refArticle->setQuantiteStock($refArticle->getQuantiteStock() + $ligneArticle->getQuantite());
                }

                // on modifie le statut des articles liés à la collecte
                $demandeCollecte = $collecte->getDemandeCollecte();

                $articles = $demandeCollecte->getArticles();
                foreach ($articles as $article) {
                    $article->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF));
                }
            }

            $this->getDoctrine()->getManager()->flush();
        }

        return $this->redirectToRoute('ordre_collecte_show', [
            'id' => $collecte->getId()
        ]);
    }

    /**
     * @Route("/api-article/{id}", name="ordre_collecte_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function apiArticle(Request $request, OrdreCollecte $collecte): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $collecte->getDemandeCollecte();

            if ($demande) {

                $rows = [];

                $ligneArticle = $this->collecteReferenceRepository->getByCollecte($demande->getId());
                foreach ($ligneArticle as $ligneArticle) {
                    /** @var CollecteReference $ligneArticle */
                    $referenceArticle = $ligneArticle->getReferenceArticle();
                    // dump($ligneArticle->getId()->getReference());
                    $rows[] = [
                        "Référence CEA" => $referenceArticle ? $referenceArticle->getReference() : ' ',
                        "Libellé" => $referenceArticle ? $referenceArticle->getLibelle() : ' ',
                        "Emplacement" => $referenceArticle ? $referenceArticle->getEmplacement()->getLabel() : '',
                        "Quantité" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ' '),
                        "Actions" => $this->renderView('ordre_collecte/datatableOrdreCollecteRow.html.twig', [
                            'id' => $ligneArticle->getId(),
                            'refArticleId' => $ligneArticle->getReferenceArticle()->getId(),
                            'modifiable' => $collecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER,
                        ])
                    ];
                }

                $articles = $this->articleRepository->getByCollecte($demande->getId());
                foreach ($articles as $article) {
                    /** @var Article $article */
                    $rows[] = [
                        'Référence CEA' => $article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                        'Libellé' => $article->getLabel(),
                        "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                        'Quantité' => $article->getQuantite(),
                        "Actions" => $this->renderView('ordre_collecte/datatableOrdreCollecteRow.html.twig', [
                            'id' => $article->getId(),
                            'modifiable' => $collecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER,
                        ])
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

    /**
     *  @Route("/creer/{id}", name="ordre_collecte_new", options={"expose"=true}, methods={"GET","POST"} )
     */
    public function new(Collecte $demandeCollecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        // on crée l'ordre de collecte
        $statut = $this->statutRepository->findOneByCategorieAndStatut(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_A_TRAITER);
        $ordreCollecte = new OrdreCollecte();
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $ordreCollecte
            ->setDate($date)
            ->setNumero('C-' . $date->format('YmdHis'))
            ->setStatut($statut)
            ->setDemandeCollecte($demandeCollecte);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($ordreCollecte);

        // on modifie le statut de la demande de collecte liée
        $demandeCollecte->setStatut($this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_A_TRAITER));

        $entityManager->flush();

        return $this->redirectToRoute('collecte_show', [
            'id' => $demandeCollecte->getId(),
        ]);
    }

    /**
     *  @Route("/modifier-article-api", name="ordre_collecte_edit_api", options={"expose"=true}, methods={"GET","POST"} )
     */
    public function apiEditArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle = $this->collecteReferenceRepository->find($data['id']);

            $json =  $this->renderView(
                'ordre_collecte/modalEditArticleContent.html.twig',
                ['ligneArticle' => $ligneArticle]
            );
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article", name="ordre_collecte_edit_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request  $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {

            $ligneArticle = $this->collecteReferenceRepository->find($data['ligneArticle']);

            $ligneArticle->setQuantite($data['quantite']);

            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}
