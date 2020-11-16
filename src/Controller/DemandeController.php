<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\LigneArticle;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\ParametrageGlobal;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Article;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\ReceptionRepository;
use App\Repository\PrefixeNomDemandeRepository;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\GlobalParamService;
use App\Service\RefArticleDataService;
use App\Service\UserService;
use App\Service\DemandeLivraisonService;
use App\Service\FreeFieldService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
{

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
                                UserService $userService,
                                RefArticleDataService $refArticleDataService,
                                ArticleDataService $articleDataService,
                                DemandeLivraisonService $demandeLivraisonService)
    {
        $this->receptionRepository = $receptionRepository;
        $this->userService = $userService;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->prefixeNomDemandeRepository = $prefixeNomDemandeRepository;
        $this->demandeLivraisonService = $demandeLivraisonService;
    }

    /**
     * @Route("/compareStock", name="compare_stock", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param DemandeLivraisonService $demandeLivraisonService
     * @param FreeFieldService $champLibreService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function compareStock(Request $request,
                                 DemandeLivraisonService $demandeLivraisonService,
                                 FreeFieldService $champLibreService,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $responseAfterQuantitiesCheck = $demandeLivraisonService->checkDLStockAndValidate(
                $entityManager,
                $data,
                false,
                $champLibreService
            );
            return new JsonResponse($responseAfterQuantitiesCheck);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="demandeLivraison_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $typeRepository = $entityManager->getRepository(Type::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);

            $demande = $demandeRepository->find($data['id']);

            $listTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);

            $typeChampLibre = [];

            $freeFieldsGroupedByTypes = [];
            foreach ($listTypes as $type) {
                $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);
                $champsLibresArray = [];
                foreach ($champsLibres as $champLibre) {
                    $champsLibresArray[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                    ];
                }
                $typeChampLibre[] = [
                    'typeLabel' => $type->getLabel(),
                    'typeId' => $type->getId(),
                    'champsLibres' => $champsLibresArray,
                ];
                $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
            }

            $json = $this->renderView('demande/modalEditDemandeContent.html.twig', [
                'demande' => $demande,
                'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
                'typeChampsLibres' => $typeChampLibre,
                'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="demande_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param FreeFieldService $champLibreService
     * @param DemandeLivraisonService $demandeLivraisonService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request,
                         FreeFieldService $champLibreService,
                         DemandeLivraisonService $demandeLivraisonService,
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
            $champLibreRepository = $entityManager->getRepository(FreeField::class);

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
                $champLibreService->manageFreeFields($demande, $data, $entityManager);
                $em->flush();
                $response = [
                    'entete' => $this->renderView('demande/demande-show-header.html.twig', [
                        'demande' => $demande,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        'showDetails' => $demandeLivraisonService->createHeaderDetailsConfig($demande)
                    ]),
                ];

            } else {
                $response['success'] = false;
                $response['msg'] = "Tous les champs obligatoires n'ont pas été renseignés.";
            }

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="demande_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param FreeFieldService $champLibreService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function new(Request $request, EntityManagerInterface $entityManager, FreeFieldService $champLibreService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
            $demande = $this->demandeLivraisonService->newDemande($data, $entityManager, $champLibreService);

            if ($demande instanceof Demande) {
                $entityManager->persist($demande);
                try {
                    $entityManager->flush();
                }
                /** @noinspection PhpRedundantCatchClauseInspection */
                catch (UniqueConstraintViolationException $e) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Une autre demande de livraison est en cours de création, veuillez réessayer.'
                    ]);
                }

                return new JsonResponse([
                    'success' => true,
                    'redirect' => $this->generateUrl('demande_show', ['id' => $demande->getId()]),
                ]);
            }
            else {
                return new JsonResponse($demande);
            }
        }
        throw new BadRequestHttpException();
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
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);

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
            'statuts' => $statutRepository->findByCategorieName(Demande::CATEGORIE),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'filterStatus' => $filter,
            'receptionFilter' => $reception,
            'livraisonLocation' => $globalParamService->getParamLocation(ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON)
        ]);
    }

    /**
     * @Route("/delete", name="demande_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param DemandeLivraisonService $demandeLivraisonService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           DemandeLivraisonService $demandeLivraisonService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $demandeRepository = $entityManager->getRepository(Demande::class);

            $demande = $demandeRepository->find($data['demandeId']);
            $preparations = $demande->getPreparations();

            if ($preparations->count() === 0) {
                $demandeLivraisonService->managePreRemoveDeliveryRequest($demande, $entityManager);
                $entityManager->remove($demande);
                $entityManager->flush();
                $data = [
                    'redirect' => $this->generateUrl('demande_index'),
                    'success' => true
                ];
            }
            else {

                $data = [
                    'message' => 'Vous ne pouvez pas supprimer cette demande, vous devez d\'abord supprimer ses ordres.',
                    'success' => false
                ];
            }
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", options={"expose"=true}, name="demande_api", methods={"POST"})
     * @param Request $request
     * @return Response
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
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/voir/{id}", name="demande_show", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param DemandeLivraisonService $demandeLivraisonService
     * @param Demande $demande
     * @return Response
     */
    public function show(EntityManagerInterface $entityManager,
                         DemandeLivraisonService $demandeLivraisonService,
                         Demande $demande): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_LIVR)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'statuts' => $statutRepository->findByCategorieName(Demande::CATEGORIE),
            'references' => $referenceArticleRepository->getIdAndLibelle(),
            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
            'finished' => ($demande->getStatut()->getNom() === Demande::STATUT_A_TRAITER),
            'showDetails' => $demandeLivraisonService->createHeaderDetailsConfig($demande)
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
                $rowsRC[] = [
                    "Référence" => ($ligneArticle->getReference()->getReference() ? $ligneArticle->getReference()->getReference() : ''),
                    "Libellé" => ($ligneArticle->getReference()->getLibelle() ? $ligneArticle->getReference()->getLibelle() : ''),
                    "Emplacement" => ($ligneArticle->getReference()->getEmplacement() ? $ligneArticle->getReference()->getEmplacement()->getLabel() : ' '),
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
            $articles = $articleRepository->findByDemandes([$demande]);
            $rowsCA = [];
            foreach ($articles as $article) {
                $rowsCA[] = [
                    "Référence" => ($article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                    "Libellé" => ($article->getLabel() ? $article->getLabel() : ''),
                    "Emplacement" => ($article->getEmplacement() ? $article->getEmplacement()->getLabel() : ' '),
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
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajouter-article", name="demande_add_article", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param FreeFieldService $champLibreService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function addArticle(Request $request, EntityManagerInterface $entityManager, FreeFieldService $champLibreService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $referenceArticle = $referenceArticleRepository->find($data['referenceArticle']);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $demande = $demandeRepository->find($data['livraison']);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $resp = $this->refArticleDataService->addRefToDemand(
                $data,
                $referenceArticle,
                $currentUser,
                false,
                $entityManager,
                $demande,
                $champLibreService
            );
            if ($resp === 'article') {
                $this->articleDataService->editArticle($data);
                $resp = true;
            }
            $entityManager->flush();
            return new JsonResponse($resp);
        }
        throw new BadRequestHttpException();
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
        throw new BadRequestHttpException();
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
        throw new BadRequestHttpException();
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
        throw new BadRequestHttpException();
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

            $articles = $articleRepository->findByDemandes([$data['id']]);
            $references = $ligneArticleRepository->findByDemandes([$data['id']]);
            $count = count($articles) + count($references);

            return new JsonResponse($count > 0);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_demandes_csv", options={"expose"=true}, methods={"GET"} )
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param CSVExportService $CSVExportService
     * @return Response
     */
    public function getDemandesCSV(EntityManagerInterface $entityManager,
                                   Request $request,
                                   FreeFieldService $freeFieldService,
                                   CSVExportService $CSVExportService): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $preparationRepository = $entityManager->getRepository(Preparation::class);
            $livraisonRepository = $entityManager->getRepository(Livraison::class);
            $ligneArticleRepository = $entityManager->getRepository(LigneArticle::class);

            $demandes = $demandeRepository->findByDates($dateTimeMin, $dateTimeMax);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_LIVRAISON]);

            // en-têtes champs fixes
            $headers = array_merge(
                [
                    'demandeur',
                    'statut',
                    'destination',
                    'commentaire',
                    'date demande',
                    'date validation',
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
                ],
                $freeFieldsConfig['freeFieldsHeader']
            );

            $firstDates = $preparationRepository->getFirstDatePreparationGroupByDemande($demandes);
            $prepartionOrders = $preparationRepository->getNumeroPrepaGroupByDemande($demandes);
            $livraisonOrders = $livraisonRepository->getNumeroLivraisonGroupByDemande($demandes);

            $articles = $articleRepository->findByDemandes($demandes, true);
            $ligneArticles = $ligneArticleRepository->findByDemandes($demandes, true);

            $nowStr = date("d-m-Y H:i");
            return $CSVExportService->createBinaryResponseFromData(
                "dem-livr $nowStr.csv",
                $demandes,
                $headers,
                function (Demande $demande)
                use (
                    $firstDates,
                    $prepartionOrders,
                    $livraisonOrders,
                    $articles,
                    $ligneArticles,
                    $freeFieldsConfig,
                    $freeFieldService
                ) {
                    $rows = [];
                    $demandeId = $demande->getId();
                    $firstDatePrepaForDemande = isset($firstDates[$demandeId]) ? $firstDates[$demandeId] : null;
                    $prepartionOrdersForDemande = isset($prepartionOrders[$demandeId]) ? $prepartionOrders[$demandeId] : [];
                    $livraisonOrdersForDemande = isset($livraisonOrders[$demandeId]) ? $livraisonOrders[$demandeId] : [];
                    $infosDemand = $this->getCSVExportFromDemand($demande, $firstDatePrepaForDemande, $prepartionOrdersForDemande, $livraisonOrdersForDemande);

                    if (isset($ligneArticles[$demandeId])) {
                        /** @var LigneArticle $ligneArticle */
                        foreach ($ligneArticles[$demandeId] as $ligneArticle) {
                            $demandeData = [];
                            $articleRef = $ligneArticle->getReference();

                            $availableQuantity = $articleRef->getQuantiteDisponible();

                            array_push($demandeData, ...$infosDemand);
                            $demandeData[] = $articleRef ? $articleRef->getReference() : '';
                            $demandeData[] = $articleRef ? $articleRef->getLibelle() : '';
                            $demandeData[] = '';
                            $demandeData[] = $articleRef ? $articleRef->getBarCode() : '';
                            $demandeData[] = $availableQuantity;
                            $demandeData[] = $ligneArticle->getQuantite();

                            $freeFields = $demande->getFreeFields();
                            foreach ($freeFieldsConfig['freeFieldIds'] as $freeFieldId) {
                                $demandeData[] = $freeFieldService->serializeValue([
                                    'typage' => $freeFieldsConfig['freeFieldsIdToTyping'][$freeFieldId],
                                    'valeur' => $freeFields[$freeFieldId] ?? ''
                                ]);
                            }
                            $rows[] = $demandeData;
                        }
                    }

                    if (isset($articles[$demandeId])) {
                        /** @var Article $article */
                        foreach ($articles[$demandeId] as $article) {
                            $demandeData = [];

                            array_push($demandeData, ...$infosDemand);
                            $demandeData[] = $article->getArticleFournisseur()->getReferenceArticle()->getReference();
                            $demandeData[] = $article->getLabel();
                            $demandeData[] = $article->getBarCode();
                            $demandeData[] = '';
                            $demandeData[] = $article->getQuantite();
                            $demandeData[] = $article->getQuantiteAPrelever();
                            $freeFields = $demande->getFreeFields();
                            foreach ($freeFieldsConfig['freeFieldIds'] as $freeFieldId) {
                                $demandeData[] = $freeFieldService->serializeValue([
                                    'typage' => $freeFieldsConfig['freeFieldsIdToTyping'][$freeFieldId],
                                    'valeur' => $freeFields[$freeFieldId] ?? ''
                                ]);
                            }
                            $rows[] = $demandeData;
                        }
                    }
                    return $rows;
                }
            );
        } else {
            throw new BadRequestHttpException();
        }
    }

    private function getCSVExportFromDemand(Demande $demande,
                                            $firstDatePrepaStr,
                                            array $preparationOrdersNumeros,
                                            array $livraisonOrders): array {
        $firstDatePrepa = isset($firstDatePrepaStr)
            ? DateTime::createFromFormat('Y-m-d H:i:s', $firstDatePrepaStr)
            : null;

        $requestCreationDate = $demande->getDate();

        return [
            $demande->getUtilisateur()->getUsername(),
            $demande->getStatut()->getNom(),
            $demande->getDestination()->getLabel(),
            strip_tags($demande->getCommentaire()),
            isset($requestCreationDate) ? $requestCreationDate->format('d/m/Y H:i:s') : '',
            isset($firstDatePrepa) ? $firstDatePrepa->format('d/m/Y H:i:s') : '',
            $demande->getNumero(),
            $demande->getType() ? $demande->getType()->getLabel() : '',
            !empty($preparationOrdersNumeros) ? implode(' / ', $preparationOrdersNumeros) : 'ND',
            !empty($livraisonOrders) ? implode(' / ', $livraisonOrders) : 'ND',
        ];
    }


    /**
     * @Route("/autocomplete", name="get_demandes", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getDemandesAutoComplete(Request $request,
                                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_LIVR)) {
                return $this->redirectToRoute('access_denied');
            }

            $demandeRepository = $entityManager->getRepository(Demande::class);
            $search = $request->query->get('term');

            return new JsonResponse([
                'results' => $demandeRepository->getIdAndLibelleBySearch($search)
            ]);
        }
        throw new BadRequestHttpException();
    }

}
