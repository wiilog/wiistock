<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Demande;
use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Article;

use App\Entity\ValeurChampLibre;
use App\Repository\CategorieCLRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\DemandeRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\ReceptionRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\PreparationRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\PrefixeNomDemandeRepository;

use App\Service\ArticleDataService;
use App\Service\RefArticleDataService;
use App\Service\UserService;
use App\Service\DemandeLivraisonService;

use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
{
    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var LigneArticleRepository
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

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;
    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var ParametreRoleRepository
     */
    private $parametreRoleRepository;

    /**
     * @var ParametreRepository
     */
    private $parametreRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var PrefixeNomDemandeRepository
     */
    private $prefixeNomDemandeRepository;

    /**
     * @var DemandeLivraisonService
     */
    private $demandeLivraisonService;

    public function __construct(ReceptionRepository $receptionRepository, PrefixeNomDemandeRepository $prefixeNomDemandeRepository, ParametreRepository $parametreRepository, ParametreRoleRepository $parametreRoleRepository, ValeurChampLibreRepository $valeurChampLibreRepository, CategorieCLRepository $categorieCLRepository, ChampLibreRepository $champLibreRepository, TypeRepository $typeRepository, PreparationRepository $preparationRepository, ArticleRepository $articleRepository, LigneArticleRepository $ligneArticleRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, UserService $userService, RefArticleDataService $refArticleDataService, ArticleDataService $articleDataService, DemandeLivraisonService $demandeLivraisonService)
    {
        $this->receptionRepository = $receptionRepository;
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->demandeRepository = $demandeRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->userService = $userService;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->preparationRepository = $preparationRepository;
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
        $this->parametreRoleRepository = $parametreRoleRepository;
        $this->parametreRepository = $parametreRepository;
        $this->prefixeNomDemandeRepository = $prefixeNomDemandeRepository;
        $this->demandeLivraisonService = $demandeLivraisonService;
    }

    /**
     * @Route("/compareStock", name="compare_stock", options={"expose"=true}, methods="GET|POST")
     */
    public function compareStock(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demande = $this->demandeRepository->find($data['demande']);

            $response = [];
            $response['status'] = false;
            // pour réf gérées par articles
            $articles = $demande->getArticles();
            foreach ($articles as $article) {
                $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                $totalQuantity = $this->articleRepository->getTotalQuantiteByRefAndStatusLabel($refArticle, Article::STATUT_ACTIF);
                $totalQuantity -= $this->referenceArticleRepository->getTotalQuantityReservedByRefArticle($refArticle);
                $treshHold = ($article->getQuantite() > $totalQuantity) ? $totalQuantity : $article->getQuantite();
                if ($article->getQuantiteAPrelever() > $treshHold) {
                    $response['stock'] = $treshHold;
                    return new JsonResponse($response);
                }
            }

            // pour réf gérées par référence
            foreach ($demande->getLigneArticle() as $ligne) {
                if (!$ligne->getToSplit()) {
                    $articleRef = $ligne->getReference();

                    $stock = $articleRef->getQuantiteStock();
                    $quantiteReservee = $ligne->getQuantite();

                    $listLigneArticleByRefArticle = $this->ligneArticleRepository->findByRefArticle($articleRef);

                    foreach ($listLigneArticleByRefArticle as $ligneArticle) {
                        $statusLabel = $ligneArticle->getDemande()->getStatut()->getNom();
                        if (in_array($statusLabel, [Demande::STATUT_A_TRAITER, Demande::STATUT_PREPARE, Demande::STATUT_INCOMPLETE])) {
                            $quantiteReservee += $ligneArticle->getQuantite();
                        }
                    }

                    if ($quantiteReservee > $stock) {
                        $response['stock'] = $stock;
                        return new JsonResponse($response);
                    }
                } else {
                    $statutDemande = $this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
                    $totalQuantity = $this->articleRepository->getTotalQuantiteByRefAndStatusLabel($ligne->getReference(), Article::STATUT_ACTIF);
                    $totalQuantity -= $this->referenceArticleRepository->getTotalQuantityReservedWithoutLigne($ligne->getReference(), $ligne, $statutDemande);
                    if ($ligne->getQuantite() > $totalQuantity) {
                        $response['stock'] = $totalQuantity;
                        return new JsonResponse($response);
                    }
                }
            }

            return $this->finish($request);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/finir", name="finish_demande", options={"expose"=true}, methods="GET|POST")
     */
    public function finish(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PREPA, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getManager();

            $demande = $this->demandeRepository->find($data['demande']);

            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();
            $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
            $preparation
                ->setNumero('P-' . $date->format('YmdHis'))
                ->setDate($date);

            $statutP = $this->statutRepository->findOneByCategorieNameAndStatutName(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
            $preparation->setStatut($statutP);

            $demande->addPreparation($preparation);
            $statutD = $this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
            $demande->setStatut($statutD);
            $em->persist($preparation);

            // modification du statut articles => en transit
            $articles = $demande->getArticles();
            foreach ($articles as $article) {
                $article->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
                $preparation->addArticle($article);
            }
            $lignesArticles = $demande->getLigneArticle();
            foreach ($lignesArticles as $ligneArticle) {
                $lignesArticlePreparation = new LigneArticlePreparation();
                $lignesArticlePreparation
                    ->setToSplit($ligneArticle->getToSplit())
                    ->setQuantitePrelevee($ligneArticle->getQuantitePrelevee())
                    ->setQuantite($ligneArticle->getQuantite())
                    ->setReference($ligneArticle->getReference())
                    ->setPreparation($preparation);
                $em->persist($lignesArticlePreparation);
                $preparation->addLigneArticlePreparation($lignesArticlePreparation);
            }
            $em->flush();

            //renvoi de l'en-tête avec modification
            $data = [
                'entete' => $this->renderView(
                    'demande/enteteDemandeLivraison.html.twig',
                    [
                        'demande' => $demande,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        'champsLibres' => $this->valeurChampLibreRepository->getByDemandeLivraison($demande)
                    ]
                ),
                'status' => true
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="demandeLivraison_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $this->demandeRepository->find($data['id']);

            $listTypes = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);
            $typeChampLibre = [];

            foreach ($listTypes as $type) {
                $champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);
                $champsLibresArray = [];
                foreach ($champsLibres as $champLibre) {
                    $valeurChampDL = $this->valeurChampLibreRepository->getValueByDemandeLivraisonAndChampLibre($demande, $champLibre);
                    $champsLibresArray[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampDL,
                    ];
                }
                $typeChampLibre[] = [
                    'typeLabel' => $type->getLabel(),
                    'typeId' => $type->getId(),
                    'champsLibres' => $champsLibresArray,
                ];
            }

            $json = $this->renderView('demande/modalEditDemandeContent.html.twig', [
                'demande' => $demande,
                'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON),
                'typeChampsLibres' => $typeChampLibre
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="demande_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            // vérification des champs Libres obligatoires
            $requiredEdit = true;
            $type = $this->typeRepository->find(intval($data['type']));
            $CLRequired = $this->champLibreRepository->getByTypeAndRequiredEdit($type);
            foreach ($CLRequired as $CL) {
                if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                    $requiredEdit = false;
                }
            }

            if ($requiredEdit) {
                $utilisateur = $this->utilisateurRepository->find(intval($data['demandeur']));
                $emplacement = $this->emplacementRepository->find(intval($data['destination']));
                $type = $this->typeRepository->find(intval($data['type']));
                $demande = $this->demandeRepository->find($data['demandeId']);
                $demande
                    ->setUtilisateur($utilisateur)
                    ->setDestination($emplacement)
                    ->setType($type)
                    ->setCommentaire($data['commentaire']);
                $em = $this->getDoctrine()->getManager();
                $em->flush();

                // modification ou création des champs libres
                $champsLibresKey = array_keys($data);

                foreach ($champsLibresKey as $champ) {
                    if (gettype($champ) === 'integer') {
                        $valeurChampLibre = $this->valeurChampLibreRepository->findOneByDemandeLivraisonAndChampLibre($demande, $champ);

                        // si la valeur n'existe pas, on la crée
                        if (!$valeurChampLibre) {
                            $valeurChampLibre = new ValeurChampLibre();
                            $valeurChampLibre
                                ->addDemandesLivraison($demande)
                                ->setChampLibre($this->champLibreRepository->find($champ));
                            $em->persist($valeurChampLibre);
                        }
                        $valeurChampLibre->setValeur(is_array($data[$champ]) ? implode(";", $data[$champ]) : $data[$champ]);
                        $em->flush();
                    }
                }

                $response = [
                    'entete' => $this->renderView('demande/enteteDemandeLivraison.html.twig', [
                        'demande' => $demande,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        'champsLibres' => $this->valeurChampLibreRepository->getByDemandeLivraison($demande)
                    ]),
                ];

            } else {
                $response['success'] = false;
                $response['msg'] = "Tous les champs obligatoires n'ont pas été renseignés.";
            }

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer", name="demande_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            return new JsonResponse($this->demandeLivraisonService->newDemande($data));
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/liste/{reception}/{filter}", name="demande_index", methods="GET|POST", options={"expose"=true})
     * @param string|null $reception
     * @param string|null $filter
     * @return Response
     */
    public function index($reception = null, $filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $types = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);

        $typeChampLibre = [];
        foreach ($types as $type) {
            $champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }

        return $this->render('demande/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON),
            'filterStatus' => $filter,
            'receptionFilter' => $reception
        ]);
    }

    /**
     * @Route("/delete", name="demande_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $demande = $this->demandeRepository->find($data['demandeId']);
            $preparations = $demande->getPreparations();
            if (empty($preparations)) {
                // TODO prepaPartielle on doit supprimer les lignes articles aussi ??
                foreach ($demande->getArticles() as $article) {
                    $article->setDemande(null);
                }
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($demande);
                $entityManager->flush();
                $data = [
                    'redirect' => $this->generateUrl('demande_index'),
                ];
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api", options={"expose"=true}, name="demande_api", methods={"POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {

            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            // cas d'un filtre statut depuis page d'accueil
            $filterStatus = $request->request->get('filterStatus');
            $filterReception = $request->request->get('filterReception');
            $data = $this->demandeLivraisonService->getDataForDatatable($request->request, $filterStatus, $filterReception);

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/voir/{id}", name="demande_show", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function show(Demande $demande): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $valeursChampLibre = $this->valeurChampLibreRepository->getByDemandeLivraison($demande);

        return $this->render('demande/show.html.twig', [

            'demande' => $demande,
            //'preparation' => $this->preparationRepository->findOneByPreparation($demande),
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'references' => $this->referenceArticleRepository->getIdAndLibelle(),
            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
            'emplacements' => $this->emplacementRepository->findAll(),
            'finished' => ($demande->getStatut()->getNom() === Demande::STATUT_A_TRAITER),
            'champsLibres' => $valeursChampLibre
        ]);
    }

    /**
     * @Route("/api/{id}", name="demande_article_api", options={"expose"=true},  methods="GET|POST")
     */
    public function articleApi(Request $request, Demande $demande): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticles = $demande->getLigneArticle();
            $rowsRC = [];
            foreach ($ligneArticles as $ligneArticle) {
                $articleRef = $ligneArticle->getReference();
                $statutArticleActif = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
                $totalQuantity = 0;
                if ($articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                    foreach ($articleRef->getArticlesFournisseur() as $articleFournisseur) {
                        $quantity = 0;
                        foreach ($articleFournisseur->getArticles() as $article) {
                            if ($article->getStatut() == $statutArticleActif) $quantity += $article->getQuantite();
                        }
                        $totalQuantity += $quantity;
                    }
                }
                $quantity = ($articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) ? $articleRef->getQuantiteStock() : $totalQuantity;
                $availableQuantity = $quantity - $this->referenceArticleRepository->getTotalQuantityReservedByRefArticle($articleRef);

                $rowsRC[] = [
                    "Référence" => ($ligneArticle->getReference()->getReference() ? $ligneArticle->getReference()->getReference() : ''),
                    "Libellé" => ($ligneArticle->getReference()->getLibelle() ? $ligneArticle->getReference()->getLibelle() : ''),
                    "Emplacement" => ($ligneArticle->getReference()->getEmplacement() ? $ligneArticle->getReference()->getEmplacement()->getLabel() : ' '),
                    "Quantité" => $availableQuantity,
                    "Quantité à prélever" => $ligneArticle->getQuantite() ?? '',
                    "Actions" => $this->renderView(
                        'demande/datatableLigneArticleRow.html.twig',
                        [
                            'id' => $ligneArticle->getId(),
                            'name' => (ReferenceArticle::TYPE_QUANTITE_REFERENCE),
                            'refArticleId' => $ligneArticle->getReference()->getId(),
                            'reference' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
                            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        ]
                    )
                ];
            }
            $articles = $this->articleRepository->findByDemande($demande);
            $rowsCA = [];
            foreach ($articles as $article) {
                $rowsCA[] = [
                    "Référence" => ($article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                    "Libellé" => ($article->getLabel() ? $article->getLabel() : ''),
                    "Emplacement" => ($article->getEmplacement() ? $article->getEmplacement()->getLabel() : ' '),
                    "Quantité" => ($article->getQuantite() ? $article->getQuantite() : ''),
                    "Quantité à prélever" => ($article->getQuantiteAPrelever() ? $article->getQuantiteAPrelever() : ''),
                    "Actions" => $this->renderView(
                        'demande/datatableLigneArticleRow.html.twig',
                        [
                            'id' => $article->getId(),
                            'name' => (ReferenceArticle::TYPE_QUANTITE_ARTICLE),
                            'reference' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
                            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        ]
                    ),
                ];
            }

            $data['data'] = array_merge($rowsCA, $rowsRC);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajouter-article", name="demande_add_article", options={"expose"=true},  methods="GET|POST")
     */
    public function addArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $referenceArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            $resp = $this->refArticleDataService->addRefToDemand($data, $referenceArticle);

            if ($resp === 'article') {
                $this->articleDataService->editArticle($data);
                $resp = true;
            }

            return new JsonResponse($resp);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/retirer-article", name="demande_remove_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function removeArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $entityManager = $this->getDoctrine()->getManager();
            if (array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $ligneAricle = $this->ligneArticleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_REFERENCE]);
                $entityManager->remove($ligneAricle);
            } elseif (array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $article = $this->articleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_ARTICLE]);
                $demande = $article->getDemande();
                $demande->removeArticle($article);
                $article->setQuantitePrelevee(0);
                $article->setQuantiteAPrelever(0);
            }
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-article", name="demande_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $ligneArticle = $this->ligneArticleRepository->find($data['ligneArticle']);
            $ligneArticle->setQuantite(max($data["quantite"], 0)); // protection contre quantités négatives
            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier-article", name="demande_article_api_edit", options={"expose"=true}, methods={"POST"})
     */
    public function articleEditApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle = $this->ligneArticleRepository->find($data['id']);
            $articleRef = $ligneArticle->getReference();
            $statutArticleActif = $this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_ACTIF);
            $qtt = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE ?
                $this->articleRepository->getTotalQuantiteFromRefNotInDemand($articleRef, $statutArticleActif) :
                $articleRef->getQuantiteStock();
            $json = $this->renderView('demande/modalEditArticleContent.html.twig', [
                'ligneArticle' => $ligneArticle,
                'maximum' => $qtt,
                'toSplit' => $ligneArticle->getToSplit()
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/non-vide", name="demande_livraison_has_articles", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function hasArticles(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articles = $this->articleRepository->findByDemande($data['id']);
            $references = $this->ligneArticleRepository->findByDemande($data['id']);
            $count = count($articles) + count($references);

            return new JsonResponse($count > 0);
        }
        throw new NotFoundHttpException('404');
    }

	/**
	 * @Route("/livraison-infos", name="get_livraisons_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 */
	public function getLivraisonIntels(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

			$livraisons = $this->demandeRepository->findByDates($dateTimeMin, $dateTimeMax);

            // en-têtes champs fixes
            $headers = [
				'demandeur',
				'statut',
				'destination',
				'commentaire',
				'date demande',
				'date(s) validation(s)',
				'numéro',
				'type demande',
				'code(s) préparation(s)',
				'code(s) livraison(s)',
				'référence article',
				'libellé article',
				'quantité disponible',
				'quantité à prélever'
			];

			// en-têtes champs libres DL
			$clDL = $this->champLibreRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_LIVRAISON]);
			foreach ($clDL as $champLibre) {
				$headers[] = $champLibre->getLabel();
			}

			// en-têtes champs libres articles
			$clAR = $this->champLibreRepository->findByCategoryTypeLabels([CategoryType::ARTICLE]);
			foreach ($clAR as $champLibre) {
				$headers[] = $champLibre->getLabel();
			}

			$data = [];
			$data[] = $headers;
			$listTypesArt = $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE);
			$listTypesDL = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);

			$listChampsLibresDL = [];
			foreach ($listTypesDL as $type) {
				$listChampsLibresDL = array_merge($listChampsLibresDL, $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON));
			}

			foreach ($livraisons as $livraison) {
			    $infosDemand = $this->getCSVExportFromDemand($livraison);
				foreach ($livraison->getLigneArticle() as $ligneArticle) {
					$livraisonData = [];
					$articleRef = $ligneArticle->getReference();

					$quantiteStock = ($articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE)
						? $this->articleRepository->getTotalQuantiteByRefAndStatusLabel($articleRef, Article::STATUT_ACTIF)
						: $articleRef->getQuantiteStock();

					$availableQuantity = $quantiteStock - $this->referenceArticleRepository->getTotalQuantityReservedByRefArticle($articleRef);

                    $livraisonOrders = $livraison
                        ->getLivraisons()
                        ->map(function (Livraison $livraison) {
                            return $livraison->getNumero();
                        })
                        ->toArray();
                    $preparationOrders = $livraison->getPreparations();
                    $preparationOrdersNumeros = $preparationOrders
                        ->map(function (Preparation $preparation) {
                            return $preparation->getNumero();
                        })
                        ->toArray();

					// TODO prepaPartielle attention pas le meme nombre de colonne comparé aux articles ???????  ????? $preparationOrdersNumeros & $livraisonOrders pour les articles aussi
                    array_push($livraisonData, ...$infosDemand);
					$livraisonData[] = !empty($preparationOrdersNumeros) ? implode(' / ', $preparationOrdersNumeros) : 'ND';
					$livraisonData[] = !empty($livraisonOrders) ? implode(' / ', $livraisonOrders) : 'ND';
					$livraisonData[] = $ligneArticle->getReference() ? $ligneArticle->getReference()->getReference() : '';
					$livraisonData[] = $ligneArticle->getReference() ? $ligneArticle->getReference()->getLibelle() : '';
					$livraisonData[] = $availableQuantity;
					$livraisonData[] = $ligneArticle->getQuantite();

					// champs libres de la demande
					$this->addChampsLibresDL($livraison, $listChampsLibresDL, $clDL, $livraisonData);

					// champs libres de l'article de référence
					$categorieCLLabel = $ligneArticle->getReference()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE ? CategorieCL::REFERENCE_ARTICLE : CategorieCL::ARTICLE;
					$champsLibresArt = [];

					foreach ($listTypesArt as $type) {
						$listChampsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, $categorieCLLabel);
						foreach ($listChampsLibres as $champLibre) {
							$valeurChampRefArticle = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($ligneArticle->getReference()->getId(), $champLibre);
							if ($valeurChampRefArticle) {
								$champsLibresArt[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
							}
						}
					}
					foreach ($clAR as $type) {
						if (array_key_exists($type->getLabel(), $champsLibresArt)) {
							$livraisonData[] = $champsLibresArt[$type->getLabel()];
						} else {
							$livraisonData[] = '';
						}
					}

					$data[] = $livraisonData;
				}
				foreach ($this->articleRepository->findByDemande($livraison) as $article) {
					$livraisonData = [];

                    // TODO prepaPartielle attention pas le meme nombre de colonne comparé aux articles ???????  ????? $preparationOrdersNumeros & $livraisonOrders pour les articles aussi
                    array_push($livraisonData, ...$infosDemand);
					$livraisonData[] = $article->getArticleFournisseur()->getReferenceArticle()->getReference();
					$livraisonData[] = $article->getLabel();
					$livraisonData[] = $article->getQuantite();

					// champs libres de la demande
					$this->addChampsLibresDL($livraison, $listChampsLibresDL, $clDL, $livraisonData);

					// champs libres de l'article
					$champsLibresArt = [];
					foreach ($listTypesArt as $type) {
						$listChampsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::ARTICLE);
						foreach ($listChampsLibres as $champLibre) {
							$valeurChampRefArticle = $this->valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champLibre);
							if ($valeurChampRefArticle) {
								$champsLibresArt[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
							}
						}
					}
					foreach ($clAR as $type) {
						if (array_key_exists($type->getLabel(), $champsLibresArt)) {
							$livraisonData[] = $champsLibresArt[$type->getLabel()];
						} else {
							$livraisonData[] = '';
						}
					}

					$data[] = $livraisonData;
				}
			}
			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
	}

	/**
	 * @param Demande $livraison
	 * @param ChampLibre[] $listChampsLibresDL
	 * @param ChampLibre[] $cls
	 * @param array $livraisonData
	 * @throws NonUniqueResultException
	 */
	private function addChampsLibresDL($livraison, $listChampsLibresDL, $cls, &$livraisonData)
	{
		$champsLibresDL = [];
		foreach ($listChampsLibresDL as $champLibre) {
			$valeurChampDL = $this->valeurChampLibreRepository->findOneByDemandeLivraisonAndChampLibre($livraison, $champLibre);
			if ($valeurChampDL) {
				$champsLibresDL[$champLibre->getLabel()] = $valeurChampDL->getValeur();
			}
		}

		foreach ($cls as $cl) {
			if (array_key_exists($cl->getLabel(), $champsLibresDL)) {
				$livraisonData[] = $champsLibresDL[$cl->getLabel()];
			} else {
				$livraisonData[] = '';
			}
		}
	}

    private function getCSVExportFromDemand(Demande $demande): array {
        $preparationOrders = $demande->getPreparations();
        return [
            $demande->getUtilisateur()->getUsername(),
            $demande->getStatut()->getNom(),
            $demande->getDestination()->getLabel(),
            strip_tags($demande->getCommentaire()),
            $demande->getDate()->format('Y/m/d-H:i:s'),
            !empty($preparationOrders) ? $preparationOrders->last()->getDate()->format('Y/m/d-H:i:s') : '',
            $demande->getNumero(),
            $demande->getType() ? $demande->getType()->getLabel() : ''
        ];
    }


    /**
     * @Route("/autocomplete", name="get_demandes", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param DemandeRepository $demandeRepository
     * @return Response
     */
    public function getDemandesAutoComplete(Request $request,
                                            DemandeRepository $demandeRepository): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $search = $request->query->get('term');
            return new JsonResponse([
                'results' => $demandeRepository->getIdAndLibelleBySearch($search)
            ]);
        }
        throw new NotFoundHttpException("404");
    }

}
