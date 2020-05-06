<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\LigneArticlePreparation;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Repository\UtilisateurRepository;
use App\Service\PDFGeneratorService;
use App\Service\PreparationsManagerService;
use App\Service\RefArticleDataService;
use App\Service\SpecificService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ArticleDataService;
use App\Entity\Demande;
use App\Repository\LivraisonRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var SpecificService
     */
    private $specificService;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var PreparationsManagerService
     */
    private $preparationsManagerService;

    public function __construct(PreparationsManagerService $preparationsManagerService,
                                UtilisateurRepository $utilisateurRepository,
                                SpecificService $specificService,
                                LivraisonRepository $livraisonRepository,
                                ArticleDataService $articleDataService,
                                UserService $userService)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->specificService = $specificService;
        $this->preparationsManagerService = $preparationsManagerService;
    }


    /**
     * @Route("/finish/{idPrepa}", name="preparation_finish", methods={"POST"})
     * @param $idPrepa
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PreparationsManagerService $preparationsManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws NoResultException
     */
    public function finishPrepa($idPrepa,
                                Request $request,
                                EntityManagerInterface $entityManager,
                                PreparationsManagerService $preparationsManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $preparation = $preparationRepository->find($idPrepa);
        $locationEndPrepa = $emplacementRepository->find($request->request->get('emplacement'));

        $articlesNotPicked = $preparationsManager->createMouvementsPrepaAndSplit($preparation, $this->getUser());

        $dateEnd = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $livraison = $preparationsManager->createLivraison($dateEnd, $preparation);
        $entityManager->persist($livraison);
        $preparationsManager->treatPreparation($preparation, $this->getUser(), $locationEndPrepa, $articlesNotPicked);
        $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $locationEndPrepa);

        $mouvementRepository = $entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementRepository->findByPreparation($preparation);
        $entityManager->flush();

        foreach ($mouvements as $mouvement) {
            $preparationsManager->createMouvementLivraison(
                $mouvement->getQuantity(),
                $this->getUser(),
                $livraison,
                !empty($mouvement->getRefArticle()),
                $mouvement->getRefArticle() ?? $mouvement->getArticle(),
                $preparation,
                false,
                $locationEndPrepa
            );
        }

        $entityManager->flush();

        $preparationsManager->updateRefArticlesQuantities($preparation);

        return $this->redirectToRoute('livraison_show', [
            'id' => $livraison->getId(),
        ]);
    }

    /**
     * @Route("/creer", name="", methods="POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
        {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);

            $preparation = new Preparation();
            $entityManager = $this->getDoctrine()->getManager();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $preparation->setNumero('P-' . $date->format('YmdHis'));
            $preparation->setDate($date);
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
            $preparation->setStatut($statut);

            foreach ($data as $key) {
                $demande = $demandeRepository->find($key);
                $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
                $demande
                    ->addPreparation($preparation)
                    ->setStatut($statut);
                $articles = $demande->getArticles();
                foreach ($articles as $article) {
                    $article->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
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
                    $entityManager->persist($lignesArticlePreparation);
                    $preparation->addLigneArticlePreparation($lignesArticlePreparation);
                }
            }

            $entityManager->persist($preparation);
            $entityManager->flush();

            $data = [
                "preparation" => [
                    "id" => $preparation->getId(),
                    "numero" => $preparation->getNumero(),
                    "date" => $preparation->getDate()->format("d/m/Y H:i:s"),
                    "Statut" => $preparation->getStatut()->getNom()
                ],
                "message" => "Votre préparation à été enregistrée."
            ];
            $data = json_encode($data);
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/liste/{demandId}", name="preparation_index", methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param string|null $demandId
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager,
                          string $demandId = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA)) {
            return $this->redirectToRoute('access_denied');
        }

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);

        $demandeLivraison = $demandId ? $demandeRepository->find($demandId) : null;

        return $this->render('preparation/index.html.twig', [
            'filterDemandId' => isset($demandeLivraison) ? $demandId : null,
            'filterDemandValue' => isset($demandeLivraison) ? $demandeLivraison->getNumero() : null,
            'filtersDisabled' => isset($demandeLivraison),
            'displayDemandFilter' => true,
            'statuts' => $statutRepository->findByCategorieName(Preparation::CATEGORIE),
            'types' => $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON)
        ]);
    }

    /**
     * @Route("/api", name="preparation_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA)) {
                return $this->redirectToRoute('access_denied');
            }

            $filterDemand = $request->request->get('filterDemand');
            $data = $this->preparationsManagerService->getDataForDatatable($request->request, $filterDemand);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api_article/{preparation}", name="preparation_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param Preparation $preparation
     * @return Response
     */
    public function apiLignePreparation(Request $request,
                                        Preparation $preparation): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $preparation->getDemande();
            $preparationStatut = $preparation->getStatut() ? $preparation->getStatut()->getNom() : null;
            $isPrepaEditable = $preparationStatut === Preparation::STATUT_A_TRAITER || ($preparationStatut == Preparation::STATUT_EN_COURS_DE_PREPARATION && $preparation->getUtilisateur() == $this->getUser());

            if (isset($demande)) {
                $rows = [];
                foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
                    $articleRef = $ligneArticle->getReference();
                    $isRefByArt = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE;
                    if ($ligneArticle->getQuantitePrelevee() > 0 ||
                        ($preparationStatut !== Preparation::STATUT_PREPARE && $preparationStatut !== Preparation::STATUT_INCOMPLETE)) {
                        $qttForCurrentLine = $ligneArticle->getQuantite() ?? null;
                        $rows[] = [
                            "Référence" => $articleRef ? $articleRef->getReference() : ' ',
                            "Libellé" => $articleRef ? $articleRef->getLibelle() : ' ',
                            "Emplacement" => $articleRef ? ($articleRef->getEmplacement() ? $articleRef->getEmplacement()->getLabel() : '') : '',
                            "Quantité" => $articleRef->getQuantiteStock(),
                            "Quantité à prélever" => $qttForCurrentLine,
                            "Quantité prélevée" => $ligneArticle->getQuantitePrelevee() ? $ligneArticle->getQuantitePrelevee() : ' ',
                            "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                                'barcode' => $articleRef->getBarCode(),
                                'isRef' => true,
                                'artOrRefId' => $articleRef->getId(),
                                'isRefByArt' => $isRefByArt,
                                'id' => $ligneArticle->getId(),
                                'isPrepaEditable' => $isPrepaEditable,
                                'active' => !empty($ligneArticle->getQuantitePrelevee())
                            ])
                        ];
                    }
                }

                foreach ($preparation->getArticles() as $article) {
                    if ($article->getQuantite() > 0 ||
                        ($preparationStatut !== Preparation::STATUT_PREPARE && $preparationStatut !== Preparation::STATUT_INCOMPLETE)) {
                        if (empty($article->getQuantiteAPrelever())) {
                            $article->setQuantiteAPrelever($article->getQuantite());
                            $this->getDoctrine()->getManager()->flush();
                        }
                        $rows[] = [
                            "Référence" => ($article->getArticleFournisseur() && $article->getArticleFournisseur()->getReferenceArticle()) ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                            "Libellé" => $article->getLabel() ?? '',
                            "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                            "Quantité" => $article->getQuantite() ?? '',
                            "Quantité à prélever" => $article->getQuantiteAPrelever() ?? '',
                            "Quantité prélevée" => $article->getQuantitePrelevee() ?? ' ',
                            "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                                'barcode' => $article->getBarCode(),
                                'artOrRefId' => $article->getId(),
                                'isRef' => false,
                                'isRefByArt' => false,
                                'quantity' => $article->getQuantiteAPrelever(),
                                'id' => $article->getId(),
                                'isPrepaEditable' => $isPrepaEditable,
                                'active' => !empty($article->getQuantitePrelevee())
                            ])
                        ];
                    }
                }

                $data['data'] = $rows;
            } else {
                $data = false; //TODO gérer affichage erreur
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="preparation_show", methods="GET|POST")
     * @param Preparation $preparation
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function show(Preparation $preparation,
                         EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA)) {
            return $this->redirectToRoute('access_denied');
        }

        $articleRepository = $entityManager->getRepository(Article::class);

        $preparationStatus = $preparation->getStatut() ? $preparation->getStatut()->getNom() : null;

        $demande = $preparation->getDemande();
        $destination = $demande ? $demande->getDestination() : null;
        $operator = $preparation ? $preparation->getUtilisateur() : null;
        $requester = $demande ? $demande->getUtilisateur() : null;
        $comment = $preparation->getCommentaire();

        return $this->render('preparation/show.html.twig', [
            'demande' => $demande,
            'livraison' => $preparation->getLivraison(),
            'preparation' => $preparation,
            'isPrepaEditable' => $preparationStatus === Preparation::STATUT_A_TRAITER || ($preparationStatus == Preparation::STATUT_EN_COURS_DE_PREPARATION && $preparation->getUtilisateur() == $this->getUser()),
            'articles' => $articleRepository->getIdRefLabelAndQuantity(),
            'headerConfig' => [
                [ 'label' => 'Numéro', 'value' => $preparation->getNumero() ],
                [ 'label' => 'Statut', 'value' => $preparation->getStatut() ? ucfirst($preparation->getStatut()->getNom()) : '' ],
                [ 'label' => 'Point de livraison', 'value' => $destination ? $destination->getLabel() : '' ],
                [ 'label' => 'Opérateur', 'value' => $operator ? $operator->getUsername() : '' ],
                [ 'label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : '' ],
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ],
            ]
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="preparation_delete", methods="GET|POST")
     * @param Preparation $preparation
     * @param EntityManagerInterface $entityManager
     * @param RefArticleDataService $refArticleDataService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function delete(Preparation $preparation,
                           EntityManagerInterface $entityManager,
                           RefArticleDataService $refArticleDataService): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $demande = $preparation->getDemande();
        if ($demande->getPreparations()->count() === 1) {
            $demande
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON));
        }

        $statutActifArticle = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
        foreach ($preparation->getArticles() as $article) {
            $article->setPreparation(null);
            $article->setStatut($statutActifArticle);
            if ($article->getQuantiteAPrelever()) {
                $article->setQuantite($article->getQuantiteAPrelever());
                $article->setQuantiteAPrelever(0);
                $article->setQuantitePrelevee(0);
            }
        }

        $refToUpdate = [];

        foreach ($preparation->getLigneArticlePreparations() as $ligneArticlePreparation) {
            $refArticle = $ligneArticlePreparation->getReference();
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $quantiteReservee = $refArticle->getQuantiteReservee();
                $quantiteAPrelever = $ligneArticlePreparation->getQuantite();
                $newQuantiteReservee = ($quantiteReservee - $quantiteAPrelever);
                $refArticle->setQuantiteReservee($newQuantiteReservee > 0 ? $newQuantiteReservee : 0);

                $newQuantiteReservee = $refArticle->getQuantiteReservee();
                $quantiteStock = $refArticle->getQuantiteStock();
                $newQuantiteDisponible = ($quantiteStock - $newQuantiteReservee);
                $refArticle->setQuantiteDisponible($newQuantiteDisponible > 0 ? $newQuantiteDisponible : 0);
            }
            else {
                $refToUpdate[] = $refArticle;
            }
            $entityManager->remove($ligneArticlePreparation);
        }

        $entityManager->remove($preparation);

        // il faut que la preparation soit supprimée avant une maj des articles
        $entityManager->flush();

        foreach ($refToUpdate as $reference) {
            $refArticleDataService->updateRefArticleQuantities($reference);
        }

        $entityManager->flush();

        return $this->redirectToRoute('preparation_index');
    }

    /**
     * @Route("/commencer-scission", name="start_splitting", options={"expose"=true}, methods="GET|POST")
     * Get list of article
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function startSplitting(EntityManagerInterface $entityManager,
                                   Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $ligneArticleId = json_decode($request->getContent(), true)) {

            $statutRepository = $entityManager->getRepository(Statut::class);
            $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);
            $articleRepository = $entityManager->getRepository(Article::class);

            $ligneArticle = $ligneArticlePreparationRepository->find($ligneArticleId);

            $refArticle = $ligneArticle->getReference();
            $statutArticleActif = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
            $preparation = $ligneArticle->getPreparation();
            $articles = $articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statutArticleActif, $preparation, $preparation->getDemande());
            $response = $this->renderView('preparation/modalSplitting.html.twig', [
                'reference' => $refArticle->getReference(),
                'referenceId' => $refArticle->getId(),
                'articles' => $articles,
                'quantite' => $ligneArticle->getQuantite(),
                'preparation' => $preparation,
                'demande' => $preparation->getDemande()
            ]);

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/finir-scission", name="submit_splitting", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function submitSplitting(Request $request,
                                    EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $totalQuantity = 0;
            foreach ($data['articles'] as $id => $quantity) {
                $totalQuantity += $quantity;
            }
            if (!empty($data['articles'])) {
                $articleRepository = $entityManager->getRepository(Article::class);
                $preparationRepository = $entityManager->getRepository(Preparation::class);
                $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);

                $preparation = $preparationRepository->find($data['preparation']);
                $articleFirst = $articleRepository->find(array_key_first($data['articles']));
                $refArticle = $articleFirst->getArticleFournisseur()->getReferenceArticle();
                $ligneArticle = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                foreach ($data['articles'] as $idArticle => $quantite) {
                    $article = $articleRepository->find($idArticle);
                    $this->preparationsManagerService->treatArticleSplitting($article, $quantite, $ligneArticle);
                }
                $this->preparationsManagerService->deleteLigneRefOrNot($ligneArticle);
                $entityManager->flush();
                $resp = true;
            } else {
                $resp = false;
            }
            return new JsonResponse($resp);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-article", name="prepa_edit_ligne_article", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editLigneArticle(Request $request,
                                     EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $articleRepository = $entityManager->getRepository(Article::class);
        $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($data['isRef']) {
                $ligneArticle = $ligneArticlePreparationRepository->find($data['ligneArticle']);
            } else {
                $ligneArticle = $articleRepository->find($data['ligneArticle']);
            }

            if ($ligneArticle instanceof Article) {
                $ligneRef = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($ligneArticle->getArticleFournisseur()->getReferenceArticle(), $ligneArticle->getPreparation());

                if (isset($ligneRef)) {
                    $ligneRef->setQuantite($ligneRef->getQuantite() + ($ligneArticle->getQuantitePrelevee() - intval($data['quantite'])));
                }
            }
            // protection contre quantités négatives
            if (isset($data['quantite'])) {
                $ligneArticle->setQuantitePrelevee(max($data['quantite'], 0));
            }
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article-api", name="prepa_edit_api", options={"expose"=true}, methods={"GET","POST"} )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEditLigneArticle(Request $request,
                                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleRepository = $entityManager->getRepository(Article::class);
            $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);

            if ($data['ref']) {
                $ligneArticle = $ligneArticlePreparationRepository->find($data['id']);
                $quantity = $ligneArticle->getQuantite();
            } else {
                $article = $articleRepository->find($data['id']);
                $quantity = $article->getQuantitePrelevee();
            }

            $json = $this->renderView(
                'preparation/modalEditLigneArticleContent.html.twig',
                [
                    'isRef' => $data['ref'],
                    'quantity' => $quantity,
                    'max' => $data['ref']
                        ? $quantity
                        : (isset($article) ? $article->getQuantiteAPrelever() : null)
                ]
            );

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/commencer-preparation", name="prepa_begin", options={"expose"=true}, methods={"GET","POST"} )
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function beginPrepa(EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $prepaId = json_decode($request->getContent(), true)) {

            $statutRepository = $entityManager->getRepository(Statut::class);
            $preparationRepository = $entityManager->getRepository(Preparation::class);

            $preparation = $preparationRepository->find($prepaId);

            if ($preparation) {
                $statusInProgress = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
                $preparation
                    ->setStatut($statusInProgress)
                    ->setUtilisateur($this->getUser());
                $this->getDoctrine()->getManager()->flush();
            }

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/infos", name="get_ordres_prepa_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getOrdrePrepaIntels(Request $request,
                                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $preparationRepository = $entityManager->getRepository(Preparation::class);

            $preparations = $preparationRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [
                'numéro',
                'statut',
                'date création',
                'opérateur',
                'type',
                'référence',
                'libellé',
                'emplacement',
                'quantité à collecter',
                'code-barre'
            ];

            $data = [];
            $data[] = $headers;
            foreach ($preparations as $preparation) {
                $this->buildInfos($preparation, $data);
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }


    private function buildInfos(Preparation $preparation, &$data)
    {
        $demande = $preparation->getDemande();
        $dataPrepa =
            [
                $preparation->getNumero() ?? '',
                $preparation->getStatut() ? $preparation->getStatut()->getNom() : '',
                $preparation->getDate() ? $preparation->getDate()->format('d/m/Y h:i') : '',
                $preparation->getUtilisateur() ? $preparation->getUtilisateur()->getUsername() : '',
                $demande->getType() ? $demande->getType()->getLabel() : '',
            ];

        foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
            $referenceArticle = $ligneArticle->getReference();

            if ($ligneArticle->getQuantite() > 0) {
                $data[] = array_merge($dataPrepa, [
                    $referenceArticle->getReference() ?? '',
                    $referenceArticle->getLibelle() ?? '',
                    $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
                    $ligneArticle->getQuantite() ?? 0,
                    $referenceArticle->getBarCode(),
                ]);
            }
        }

    foreach ($preparation->getArticles() as $article) {
            $articleFournisseur = $article->getArticleFournisseur();
            $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
            $reference = $referenceArticle ? $referenceArticle->getReference() : '';

            if ($article->getQuantite() > 0) {
                $data[] = array_merge($dataPrepa, [
                    $reference,
                    $article->getLabel() ?? '',
                    $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                    $article->getQuantite() ?? 0,
                    $article->getBarCode(),
                ]);
            }
        }
    }

    /**
     * @Route("/{preparation}/etiquettes", name="preparation_bar_codes_print", options={"expose"=true})
     *
     * @param Preparation $preparation
     * @param RefArticleDataService $refArticleDataService
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getBarCodes(Preparation $preparation,
                                RefArticleDataService $refArticleDataService,
                                ArticleDataService $articleDataService,
                                PDFGeneratorService $PDFGeneratorService): Response
    {
        $articles = $preparation->getArticles()->toArray();
        $lignesArticle = $preparation->getLigneArticlePreparations()->toArray();
        $referenceArticles = [];

        /** @var LigneArticlePreparation $ligne */
        foreach ($lignesArticle as $ligne) {
            $reference = $ligne->getReference();
            if ($reference->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticles[] = $reference;
            }
        }
        $barcodeConfigs = array_merge(
            array_map(
                function (Article $article) use ($articleDataService) {
                    return $articleDataService->getBarcodeConfig($article);
                },
                $articles
            ),
            array_map(
                function (ReferenceArticle $referenceArticle) use ($refArticleDataService) {
                    return $refArticleDataService->getBarcodeConfig($referenceArticle);
                },
                $referenceArticles
            )
        );

        $barcodeCounter = count($barcodeConfigs);

        if ($barcodeCounter > 0) {
            $fileName = $PDFGeneratorService->getBarcodeFileName(
                $barcodeConfigs,
                'preparation'
            );

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
                $fileName
            );
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }
}
