<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\LigneArticle;
use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Article;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Repository\CategorieCLRepository;
use App\Repository\DemandeRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\ReceptionRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\PreparationRepository;
use App\Repository\PrefixeNomDemandeRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Service\ArticleDataService;
use App\Service\GlobalParamService;
use App\Service\RefArticleDataService;
use App\Service\UserService;
use App\Service\DemandeLivraisonService;
use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
{

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

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

    public function __construct(ReceptionRepository $receptionRepository,
                                PrefixeNomDemandeRepository $prefixeNomDemandeRepository,
                                ParametreRepository $parametreRepository,
                                ParametreRoleRepository $parametreRoleRepository,
                                CategorieCLRepository $categorieCLRepository,
                                PreparationRepository $preparationRepository,
                                DemandeRepository $demandeRepository,
                                UtilisateurRepository $utilisateurRepository,
                                UserService $userService,
                                RefArticleDataService $refArticleDataService,
                                ArticleDataService $articleDataService,
                                DemandeLivraisonService $demandeLivraisonService)
    {
        $this->receptionRepository = $receptionRepository;
        $this->demandeRepository = $demandeRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->preparationRepository = $preparationRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->parametreRoleRepository = $parametreRoleRepository;
        $this->parametreRepository = $parametreRepository;
        $this->prefixeNomDemandeRepository = $prefixeNomDemandeRepository;
        $this->demandeLivraisonService = $demandeLivraisonService;
    }

    /**
     * @Route("/compareStock", name="compare_stock", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function compareStock(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $ligneArticleRepository = $entityManager->getRepository(LigneArticle::class);

            $demande = $demandeRepository->find($data['demande']);

            $response = [];
            $response['status'] = false;
            // pour réf gérées par articles
            $articles = $demande->getArticles();
            foreach ($articles as $article) {
                $statutArticle = $article->getStatut();
                if (isset($statutArticle)
                    && $statutArticle->getNom() !== Article::STATUT_ACTIF) {
                    $response['message'] = "Un article de votre demande n'est plus disponible. Assurez vous que chacun des articles soit en statut disponible pour valider votre demande.";
                    return new JsonResponse($response);
                }
                else {
                    $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                    $totalQuantity = $refArticle->getQuantiteDisponible();
                    $treshHold = ($article->getQuantite() > $totalQuantity)
                        ? $totalQuantity
                        : $article->getQuantite();
                    if ($article->getQuantiteAPrelever() > $treshHold) {
                        $response['stock'] = $treshHold;
                        return new JsonResponse($response);
                    }
                }
            }

            // pour réf gérées par référence
            foreach ($demande->getLigneArticle() as $ligne) {
                if (!$ligne->getToSplit()) {
                    $articleRef = $ligne->getReference();

                    $stock = $articleRef->getQuantiteStock();
                    $quantiteReservee = $ligne->getQuantite();

                    $listLigneArticleByRefArticle = $ligneArticleRepository->findByRefArticle($articleRef);

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
                    $totalQuantity = (
                        $articleRepository->getTotalQuantiteByRefAndStatusLabel($ligne->getReference(), Article::STATUT_ACTIF)
                        - $ligne->getReference()->getQuantiteReservee()
                    );
                    if ($ligne->getQuantite() > $totalQuantity) {
                        $response['stock'] = $totalQuantity;
                        return new JsonResponse($response);
                    }
                }
            }

            return $this->finish($request, $entityManager);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function finish(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);

            $demande = $demandeRepository->find($data['demande']);

            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();
            $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
            $preparation
                ->setNumero('P-' . $date->format('YmdHis'))
                ->setDate($date);

            $statutP = $statutRepository->findOneByCategorieNameAndStatutCode(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
            $preparation->setStatut($statutP);

            $demande->addPreparation($preparation);
            $statutD = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
            $demande->setStatut($statutD);
            $entityManager->persist($preparation);

            // modification du statut articles => en transit
            $articles = $demande->getArticles();
            foreach ($articles as $article) {
                $article->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
                $preparation->addArticle($article);
            }
            $lignesArticles = $demande->getLigneArticle();
            $refArticleToUpdateQuantities = [];
            foreach ($lignesArticles as $ligneArticle) {
                $referenceArticle = $ligneArticle->getReference();
                $lignesArticlePreparation = new LigneArticlePreparation();
                $lignesArticlePreparation
                    ->setToSplit($ligneArticle->getToSplit())
                    ->setQuantitePrelevee($ligneArticle->getQuantitePrelevee())
                    ->setQuantite($ligneArticle->getQuantite())
                    ->setReference($referenceArticle)
                    ->setPreparation($preparation);
                $entityManager->persist($lignesArticlePreparation);
                if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $referenceArticle->setQuantiteReservee(($referenceArticle->getQuantiteReservee() ?? 0) + $ligneArticle->getQuantite());
                }
                else {
                    $refArticleToUpdateQuantities[] = $referenceArticle;
                }
                $preparation->addLigneArticlePreparation($lignesArticlePreparation);
            }
            $entityManager->flush();

            foreach ($refArticleToUpdateQuantities as $refArticle) {
                $this->refArticleDataService->updateRefArticleQuantities($refArticle);
            }

            $entityManager->flush();

            //renvoi de l'en-tête avec modification
            $data = [
                'entete' => $this->renderView(
                    'demande/enteteDemandeLivraison.html.twig',
                    [
                        'demande' => $demande,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        'champsLibres' => $valeurChampLibreRepository->getByDemandeLivraison($demande)
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $typeRepository = $entityManager->getRepository(Type::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);

            $demande = $demandeRepository->find($data['id']);

            $listTypes = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);

            $typeChampLibre = [];

            foreach ($listTypes as $type) {
                $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);
                $champsLibresArray = [];
                foreach ($champsLibres as $champLibre) {
                    $valeurChampDL = $valeurChampLibreRepository->getValueByDemandeLivraisonAndChampLibre($demande, $champLibre);
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
                'types' => $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON),
                'typeChampsLibres' => $typeChampLibre
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="demande_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);

            // vérification des champs Libres obligatoires
            $requiredEdit = true;
            $type = $typeRepository->find(intval($data['type']));
            $CLRequired = $champLibreRepository->getByTypeAndRequiredEdit($type);
            foreach ($CLRequired as $CL) {
                if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                    $requiredEdit = false;
                }
            }

            if ($requiredEdit) {
                $utilisateur = $utilisateurRepository->find(intval($data['demandeur']));
                $emplacement = $emplacementRepository->find(intval($data['destination']));
                $demande = $demandeRepository->find($data['demandeId']);
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
                        $valeurChampLibre = $valeurChampLibreRepository->findOneByDemandeLivraisonAndChampLibre($demande, $champ);

                        // si la valeur n'existe pas, on la crée
                        if (!$valeurChampLibre) {
                            $valeurChampLibre = new ValeurChampLibre();
                            $valeurChampLibre
                                ->addDemandesLivraison($demande)
                                ->setChampLibre($champLibreRepository->find($champ));
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
                        'champsLibres' => $valeurChampLibreRepository->getByDemandeLivraison($demande)
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
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
            return new JsonResponse($this->demandeLivraisonService->newDemande($data));
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/liste/{reception}/{filter}", name="demande_index", methods="GET|POST", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param string|null $reception
     * @param string|null $filter
     * @param GlobalParamService $globalParamService
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager,
                          GlobalParamService $globalParamService,
                          $reception = null,
                          $filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_LIVR)) {
            return $this->redirectToRoute('access_denied');
        }

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

        $types = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);

        $typeChampLibre = [];
        foreach ($types as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }

        return $this->render('demande/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $statutRepository->findByCategorieName(Demande::CATEGORIE),
            'emplacements' => $emplacementRepository->getIdAndNom(),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'filterStatus' => $filter,
            'receptionFilter' => $reception,
            'livraisonLocation' => $globalParamService->getLivraisonDefaultLocation()
        ]);
    }

    /**
     * @Route("/delete", name="demande_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $demande = $this->demandeRepository->find($data['demandeId']);
            $preparations = $demande->getPreparations();
            $entityManager = $this->getDoctrine()->getManager();
            if ($preparations->count() === 0) {
                foreach ($demande->getArticles() as $article) {
                    $article->setDemande(null);
                }
                foreach ($demande->getLigneArticle() as $ligneArticle) {
                    $entityManager->remove($ligneArticle);
                }
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

            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_LIVR)) {
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
     * @param EntityManagerInterface $entityManager
     * @param Demande $demande
     * @return Response
     */
    public function show(EntityManagerInterface $entityManager,
                         Demande $demande): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_LIVR)) {
            return $this->redirectToRoute('access_denied');
        }

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);

        $valeursChampLibre = $valeurChampLibreRepository->getByDemandeLivraison($demande);

        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $statutRepository->findByCategorieName(Demande::CATEGORIE),
            'references' => $referenceArticleRepository->getIdAndLibelle(),
            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
            'emplacements' => $emplacementRepository->findAll(),
            'finished' => ($demande->getStatut()->getNom() === Demande::STATUT_A_TRAITER),
            'champsLibres' => $valeursChampLibre
        ]);
    }

    /**
     * @Route("/api/{id}", name="demande_article_api", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param Demande $demande
     * @return Response
     */
    public function articleApi(Request $request,
                               EntityManagerInterface $entityManager,
                               Demande $demande): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_LIVR)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleRepository = $entityManager->getRepository(Article::class);

            $ligneArticles = $demande->getLigneArticle();
            $rowsRC = [];
            foreach ($ligneArticles as $ligneArticle) {
                $articleRef = $ligneArticle->getReference();
                $availableQuantity = $articleRef->getQuantiteDisponible();
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
            $articles = $articleRepository->findByDemande($demande);
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws DBALException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function addArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $referenceArticle = $referenceArticleRepository->find($data['referenceArticle']);
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function removeArticle(Request $request,
                                  EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $articleRepository = $entityManager->getRepository(Article::class);
            $ligneArticleRepository = $entityManager->getRepository(LigneArticle::class);

            if (array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $ligneAricle = $ligneArticleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_REFERENCE]);
                $entityManager->remove($ligneAricle);
            } elseif (array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $article = $articleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_ARTICLE]);
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editArticle(Request $request,
                                EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $ligneArticleRepository = $entityManager->getRepository(LigneArticle::class);
            $ligneArticle = $ligneArticleRepository->find($data['ligneArticle']);
            $ligneArticle->setQuantite(max($data["quantite"], 0)); // protection contre quantités négatives
            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier-article", name="demande_article_api_edit", options={"expose"=true}, methods={"POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function articleEditApi(EntityManagerInterface $entityManager,
                                   Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $ligneArticleRepository = $entityManager->getRepository(LigneArticle::class);

            $ligneArticle = $ligneArticleRepository->find($data['id']);
            $articleRef = $ligneArticle->getReference();
            $statutArticleActif = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
            $qtt = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE ?
                $articleRepository->getTotalQuantiteFromRefNotInDemand($articleRef, $statutArticleActif) :
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function hasArticles(Request $request,
                                EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $ligneArticleRepository = $entityManager->getRepository(LigneArticle::class);

            $articles = $articleRepository->findByDemande($data['id']);
            $references = $ligneArticleRepository->findByDemande($data['id']);
            $count = count($articles) + count($references);

            return new JsonResponse($count > 0);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/demandes-infos", name="get_demandes_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
	public function getDemandesIntels(EntityManagerInterface $entityManager,
                                      Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);


            $demandeRepository = $entityManager->getRepository(Demande::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);
            $articleRepository = $entityManager->getRepository(Article::class);

			$demandes = $demandeRepository->findByDates($dateTimeMin, $dateTimeMax);

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
                'code-barre article',
                'code-barre référence',
				'quantité disponible',
				'quantité à prélever'
			];

			// en-têtes champs libres DL
			$clDL = $champLibreRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_LIVRAISON]);
			foreach ($clDL as $champLibre) {
				$headers[] = $champLibre->getLabel();
			}

			// en-têtes champs libres articles
			$clAR = $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARTICLE]);
			foreach ($clAR as $champLibre) {
				$headers[] = $champLibre->getLabel();
			}

			$data = [];
			$data[] = $headers;

			$listTypesArt = $typeRepository->findByCategoryLabel(CategoryType::ARTICLE);
			$listTypesDL = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);

			$listChampsLibresDL = [];
			foreach ($listTypesDL as $type) {
				$listChampsLibresDL = array_merge($listChampsLibresDL, $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON));
			}

			foreach ($demandes as $demande) {
			    $infosDemand = $this->getCSVExportFromDemand($demande);
				foreach ($demande->getLigneArticle() as $ligneArticle) {
					$demandeData = [];
					$articleRef = $ligneArticle->getReference();

                    $availableQuantity = $articleRef->getQuantiteDisponible();

                    array_push($demandeData, ...$infosDemand);
					$demandeData[] = $ligneArticle->getReference() ? $ligneArticle->getReference()->getReference() : '';
                    $demandeData[] = $ligneArticle->getReference() ? $ligneArticle->getReference()->getLibelle() : '';
                    $demandeData[] = '';
                    $demandeData[] = $ligneArticle->getReference() ? $ligneArticle->getReference()->getBarCode() : '';
					$demandeData[] = $availableQuantity;
					$demandeData[] = $ligneArticle->getQuantite();

					// champs libres de la demande
					$this->addChampsLibresDL($valeurChampLibreRepository, $demande, $listChampsLibresDL, $clDL, $demandeData);

					// champs libres de l'article de référence
					$categorieCLLabel = $ligneArticle->getReference()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE ? CategorieCL::REFERENCE_ARTICLE : CategorieCL::ARTICLE;
					$champsLibresArt = [];

					foreach ($listTypesArt as $type) {
						$listChampsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, $categorieCLLabel);
						foreach ($listChampsLibres as $champLibre) {
							$valeurChampRefArticle = $valeurChampLibreRepository->findOneByRefArticleAndChampLibre($ligneArticle->getReference()->getId(), $champLibre);
							if ($valeurChampRefArticle) {
								$champsLibresArt[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
							}
						}
					}
					foreach ($clAR as $type) {
						if (array_key_exists($type->getLabel(), $champsLibresArt)) {
							$demandeData[] = $champsLibresArt[$type->getLabel()];
						} else {
							$demandeData[] = '';
						}
					}

					$data[] = $demandeData;
				}
				foreach ($articleRepository->findByDemande($demande) as $article) {
					$demandeData = [];

                    array_push($demandeData, ...$infosDemand);
					$demandeData[] = $article->getArticleFournisseur()->getReferenceArticle()->getReference();
					$demandeData[] = $article->getLabel();
                    $demandeData[] = $article->getBarCode();
                    $demandeData[] = '';
					$demandeData[] = $article->getQuantite();
					$demandeData[] = $article->getQuantiteAPrelever();

					// champs libres de la demande
					$this->addChampsLibresDL($valeurChampLibreRepository, $demande, $listChampsLibresDL, $clDL, $demandeData);

					// champs libres de l'article
					$champsLibresArt = [];
					foreach ($listTypesArt as $type) {
						$listChampsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::ARTICLE);
						foreach ($listChampsLibres as $champLibre) {
							$valeurChampRefArticle = $valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champLibre);
							if ($valeurChampRefArticle) {
								$champsLibresArt[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
							}
						}
					}
					foreach ($clAR as $type) {
						if (array_key_exists($type->getLabel(), $champsLibresArt)) {
							$demandeData[] = $champsLibresArt[$type->getLabel()];
						} else {
							$demandeData[] = '';
						}
					}

					$data[] = $demandeData;
				}
			}
			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
	}

    /**
     * @param ValeurChampLibreRepository $valeurChampLibreRepository
     * @param Demande $livraison
     * @param ChampLibre[] $listChampsLibresDL
     * @param ChampLibre[] $cls
     * @param array $demandeData
     * @throws NonUniqueResultException
     */
	private function addChampsLibresDL(ValeurChampLibreRepository $valeurChampLibreRepository, $livraison, $listChampsLibresDL, $cls, &$demandeData)
	{
		$champsLibresDL = [];
		foreach ($listChampsLibresDL as $champLibre) {
			$valeurChampDL = $valeurChampLibreRepository->findOneByDemandeLivraisonAndChampLibre($livraison, $champLibre);
			if ($valeurChampDL) {
				$champsLibresDL[$champLibre->getLabel()] = $valeurChampDL->getValeur();
			}
		}

		foreach ($cls as $cl) {
			if (array_key_exists($cl->getLabel(), $champsLibresDL)) {
                $demandeData[] = $champsLibresDL[$cl->getLabel()];
			} else {
                $demandeData[] = '';
			}
		}
	}

    private function getCSVExportFromDemand(Demande $demande): array {
        $preparationOrders = $demande->getPreparations();

        $livraisonOrders = $demande
            ->getLivraisons()
            ->map(function (Livraison $livraison) {
                return $livraison->getNumero();
            })
            ->toArray();

        $preparationOrdersNumeros = $preparationOrders
            ->map(function (Preparation $preparation) {
                return $preparation->getNumero();
            })
            ->toArray();

        return [
            $demande->getUtilisateur()->getUsername(),
            $demande->getStatut()->getNom(),
            $demande->getDestination()->getLabel(),
            strip_tags($demande->getCommentaire()),
            $demande->getDate()->format('Y/m/d-H:i:s'),
            $preparationOrders->count() > 0 ? $preparationOrders->last()->getDate()->format('Y/m/d-H:i:s') : '',
            $demande->getNumero(),
            $demande->getType() ? $demande->getType()->getLabel() : '',
            !empty($preparationOrdersNumeros) ? implode(' / ', $preparationOrdersNumeros) : 'ND',
            !empty($livraisonOrders) ? implode(' / ', $livraisonOrders) : 'ND'
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
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::LIST)) {
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
