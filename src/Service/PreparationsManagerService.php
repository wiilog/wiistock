<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Exceptions\NegativeQuantityException;
use App\Repository\ArticleRepository;
use App\Repository\StatutRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;


class PreparationsManagerService
{

    public const MOUVEMENT_DOES_NOT_EXIST_EXCEPTION = 'mouvement-does-not-exist';
    public const ARTICLE_ALREADY_SELECTED = 'article-already-selected';

    private $entityManager;
    private $articleDataService;
    private $refArticleDataService;

    /**
     * @var array
     */
    private $refMouvementsToRemove;

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security,
                                RouterInterface $router,
                                Twig_Environment $templating,
                                ArticleDataService $articleDataService,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->router = $router;
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->articleDataService = $articleDataService;
        $this->refArticleDataService = $refArticleDataService;
        $this->refMouvementsToRemove = [];
    }

    public function setEntityManager(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * On termine les mouvements de prepa
     * @param Preparation $preparation
     * @param DateTime $date
     * @param Emplacement|null $emplacement
     */
    public function closePreparationMouvement(Preparation $preparation, DateTime $date, Emplacement $emplacement = null): void
    {
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);

        $mouvements = $mouvementRepository->findByPreparation($preparation);

        foreach ($mouvements as $mouvement) {
            $mouvement->setDate($date);
            if (isset($emplacement)) {
                $mouvement->setEmplacementTo($emplacement);
            }
        }
    }

    /**
     * @param Preparation $preparation
     * @param $userNomade
     * @param Emplacement $emplacement
     * @param array $articlesToKeep
     * @param EntityManagerInterface|null $entityManager
     * @return Preparation|null
     * @throws NonUniqueResultException
     */
    public function treatPreparation(Preparation $preparation,
                                     $userNomade,
                                     Emplacement $emplacement,
                                     array $articlesToKeep,
                                     EntityManagerInterface $entityManager = null): ?Preparation
    {
        if (!isset($entityManager)) {
            $entityManager = $this->entityManager;
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $demande = $preparation->getDemande();
        foreach ($preparation->getArticles() as $article) {
            $artQuantitePrelevee = $article->getQuantitePrelevee();
            if (isset($artQuantitePrelevee) && $artQuantitePrelevee > 0) {
                $article->setEmplacement($emplacement);
            }
        }

        $isPreparationComplete = $this->isPreparationComplete($preparation);

        $prepaStatusLabel = $isPreparationComplete ? Preparation::STATUT_PREPARE : Preparation::STATUT_INCOMPLETE;
        $statutPreparePreparation = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, $prepaStatusLabel);
        $demandeStatusLabel = $isPreparationComplete ? Demande::STATUT_PREPARE : Demande::STATUT_INCOMPLETE;
        $statutPrepareDemande = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_LIVRAISON, $demandeStatusLabel);
        if ($demande->getStatut()->getNom() === Demande::STATUT_A_TRAITER) {
            $demande->setStatut($statutPrepareDemande);
        }

        $preparation
            ->setUtilisateur($userNomade)
            ->setStatut($statutPreparePreparation)
            ->setEndLocation($emplacement);

        // TODO get remaining articles and refs
        if (!$isPreparationComplete) {
            return $this->persistPreparationFromOldOne($preparation, $demande, $statutRepository, $articleRepository, $articlesToKeep, $entityManager);
        } else {
            return null;
        }
    }

    private function isPreparationComplete(Preparation $preparation)
    {
        $complete = true;

        $articles = $preparation->getArticles();
        foreach ($articles as $article) {
            if (($article->getQuantitePrelevee() < $article->getQuantiteAPrelever()) || empty($article->getQuantitePrelevee())) {
                $complete = false;
                break;
            }
        }

        if ($complete) {
            $lignesArticle = $preparation->getLigneArticlePreparations();

            foreach ($lignesArticle as $ligneArticle) {
                if ($ligneArticle->getQuantitePrelevee() < $ligneArticle->getQuantite()) {
                    $complete = false;
                    break;
                }
            }
        }

        return $complete;
    }

    /**
     * @param Preparation $preparation
     * @param Demande $demande
     * @param StatutRepository $statutRepository
     * @param ArticleRepository $articleRepository
     * @param array $listOfArticleSplitted
     * @param EntityManagerInterface|null $entityManager
     * @return Preparation
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function persistPreparationFromOldOne(Preparation $preparation,
                                                  Demande $demande,
                                                  StatutRepository $statutRepository,
                                                  ArticleRepository $articleRepository,
                                                  array $listOfArticleSplitted,
                                                  EntityManagerInterface $entityManager = null): Preparation {
        if (!isset($entityManager)) {
            $entityManager = $this->entityManager;
        }

        $newPreparation = new Preparation();
        $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $number = $this->generateNumber($date, $entityManager);
        $newPreparation
            ->setNumero($number)
            ->setDate($date)
            ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_A_TRAITER));

        $demande->addPreparation($newPreparation);
        foreach ($listOfArticleSplitted as $articleId) {
            /** @var Article $articleToKeep */
            $articleToKeep = $articleRepository->find($articleId);
            $newPreparation->addArticle($articleToKeep);
            $demande->addArticle($articleToKeep);
        }

        foreach ($preparation->getLigneArticlePreparations() as $ligneArticlePreparation) {
            $refArticle = $ligneArticlePreparation->getReference();
            $pickedQuantity = $ligneArticlePreparation->getQuantitePrelevee();
            if ($ligneArticlePreparation->getQuantite() !== $pickedQuantity) {
                $newLigneArticle = new LigneArticlePreparation();
                $selectedQuantityForPreviousLigne = $ligneArticlePreparation->getQuantitePrelevee() ?? 0;
                $newQuantity = ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE)
                    ? ($ligneArticlePreparation->getQuantite() - $selectedQuantityForPreviousLigne)
                    : $ligneArticlePreparation->getQuantite();
                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $ligneArticlePreparation->setQuantite($ligneArticlePreparation->getQuantitePrelevee() ?? 0);
                }
                $newLigneArticle
                    ->setPreparation($newPreparation)
                    ->setReference($refArticle)
                    ->setQuantite($newQuantity);

                if (empty($pickedQuantity)) {
                    $entityManager->remove($ligneArticlePreparation);
                }

                $entityManager->persist($newLigneArticle);
            }
        }

        $entityManager->persist($newPreparation);
        $entityManager->flush();

        return $newPreparation;
    }

    /**
     * @param int $quantity
     * @param Utilisateur $userNomade
     * @param Livraison $livraison
     * @param Emplacement|null $emplacementFrom
     * @param bool $isRef
     * @param string $article
     * @param Preparation $preparation
     * @param bool $isSelectedByArticle
     */
    public function createMouvementLivraison(int $quantity,
                                             Utilisateur $userNomade,
                                             Livraison $livraison,
                                             bool $isRef,
                                             $article,
                                             Preparation $preparation,
                                             bool $isSelectedByArticle,
                                             Emplacement $emplacementFrom = null)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);

        $mouvement = new MouvementStock();
        $mouvement
            ->setUser($userNomade)
            ->setQuantity($quantity)
            ->setType(MouvementStock::TYPE_SORTIE)
            ->setLivraisonOrder($livraison);

        if (isset($emplacementFrom)) {
            $mouvement->setEmplacementFrom($emplacementFrom);
        }

        $this->entityManager->persist($mouvement);

        if ($isRef) {
            $refArticle = ($article instanceof ReferenceArticle)
                ? $article
                : $referenceArticleRepository->findOneByReference($article);
            if ($refArticle) {
                /** @var MouvementStock $preparationMovement */
                $preparationMovement = $preparation->getReferenceArticleMovement($refArticle);
                $mouvement
                    ->setRefArticle($refArticle)
                    ->setQuantity($preparationMovement->getQuantity());
            }
        } else {
            $article = ($article instanceof Article)
                ? $article
                : $articleRepository->findOneByReference($article);
            if ($article) {
                /** @var MouvementStock $preparationMovement */
                $preparationMovement = $preparation->getArticleMovement($article);

                /** @var MouvementStock $stockMovement */
                $stockMovement = !$isSelectedByArticle
                    ? ($preparationMovement ?: null)
                    : null;
                // si c'est un article sélectionné par l'utilisateur :
                // on prend la quantité donnée dans le mouvement
                // sinon on prend la quantité spécifiée dans le mouvement de transfert (créé dans beginPrepa)
                $mouvementQuantity = ($isSelectedByArticle || !isset($stockMovement))
                    ? $quantity
                    : $stockMovement->getQuantity();

                $mouvement
                    ->setArticle($article)
                    ->setQuantity($mouvementQuantity);
            }
        }
    }

    public function deleteLigneRefOrNot(?LigneArticlePreparation $ligne)
    {
        if ($ligne && $ligne->getQuantite() === 0) {
            $this->entityManager->remove($ligne);
        }
    }

    /**
     * @param array $mouvement
     * @param Preparation $preparation
     * @throws Exception
     */
    public function treatMouvementQuantities($mouvement, Preparation $preparation)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $ligneArticleRepository = $this->entityManager->getRepository(LigneArticlePreparation::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        if ($mouvement['is_ref']) {
            // cas ref par ref
            $refArticle = $referenceArticleRepository->findOneByReference($mouvement['reference']);
            if ($refArticle) {
                $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                $ligneArticle->setQuantitePrelevee($mouvement['quantity']);
            }
        } else {
            // cas article
            /**
             * @var Article article
             */
            $article = $articleRepository->findOneByReference($mouvement['reference']);

            if ($article) {
                // cas ref par article
                if (isset($mouvement['selected_by_article']) && $mouvement['selected_by_article']) {
                    if ($article->getPreparation()) {
                        throw new Exception(self::ARTICLE_ALREADY_SELECTED);
                    } else {
                        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                        $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                        $this->treatArticleSplitting($article, $mouvement['quantity'], $ligneArticle);
                    }
                }

                $article
                    ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT))
                    ->setQuantitePrelevee($mouvement['quantity']);
            }
        }

        $this->entityManager->flush();
    }

    public function treatArticleSplitting(Article $article,
                                          int $quantite,
                                          LigneArticlePreparation $ligneArticle,
                                          ?Statut $statusArticle = null)
    {
        if ($quantite !== '' && $quantite > 0 && $quantite <= $article->getQuantite()) {
            if (!$article->getPreparation()) {
                $article->setQuantiteAPrelever(0);
                $article->setQuantitePrelevee(0);
            }

            if ($statusArticle) {
                $article->setStatut($statusArticle);
            }

            $article->setPreparation($ligneArticle->getPreparation());

            // si on a enlevé de la quantité à l'article : on enlève la difference à la quantité de la ligne article
            // si on a ajouté de la quantité à l'article : on enlève la ajoute à la quantité de la ligne article
            // si rien a changé on touche pas à la quantité de la ligne article
            $ligneArticle->setQuantite($ligneArticle->getQuantite() + ($article->getQuantitePrelevee() - $quantite));
            $article->setQuantiteAPrelever($quantite);
            $article->setQuantitePrelevee($quantite);
        }
    }

    /**
     * On supprime les mouvements de transfert créés pour les réf gérées à l'articles
     * (elles ont été remplacées plus haut par les mouvements de transfert des articles)
     */
    public function removeRefMouvements(): void
    {
        foreach ($this->refMouvementsToRemove as $mvtToRemove) {
            $this->entityManager->remove($mvtToRemove);
        }
        $this->refMouvementsToRemove = [];
    }

    /**
     * @param Preparation $preparation
     * @param Utilisateur $user
     * @param EntityManagerInterface $entityManager
     * @return array
     * @throws NegativeQuantityException
     */
    public function createMouvementsPrepaAndSplit(Preparation $preparation,
                                                  Utilisateur $user,
                                                  EntityManagerInterface $entityManager): array
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $articlesSplittedToKeep = [];

        $articles = $preparation->getArticles();
        foreach ($articles as $article) {
            $mouvementAlreadySaved = $preparation->getArticleMovement($article);
            if (!$mouvementAlreadySaved) {
                $quantitePrelevee = $article->getQuantitePrelevee();
                $selected = !(empty($quantitePrelevee));
                $article->setStatut(
                    $statutRepository->findOneByCategorieNameAndStatutCode(
                        Article::CATEGORIE,
                        $selected ? Article::STATUT_EN_TRANSIT : Article::STATUT_ACTIF
                    )
                );

                if ($article->getQuantite() >= $quantitePrelevee) {
                    // scission des articles dont la quantité prélevée n'est pas totale
                    if ($article->getQuantite() !== $quantitePrelevee) {
                        $newArticle = [
                            'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                            'libelle' => $article->getLabel(),
                            'prix' => $article->getPrixUnitaire(),
                            'conform' => !$article->getConform(),
                            'commentaire' => $article->getcommentaire(),
                            'quantite' => $selected ? $article->getQuantite() - $article->getQuantitePrelevee() : 0,
                            'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                            'statut' => $selected ? Article::STATUT_ACTIF : Article::STATUT_INACTIF,
                            'refArticle' => $article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getId() : ''
                        ];

                        foreach ($article->getFreeFields() as $clId => $valeurChampLibre) {
                            $newArticle[$clId] = $valeurChampLibre;
                        }
                        $insertedArticle = $this->articleDataService->newArticle($newArticle, $entityManager);
                        $entityManager->flush();
                        if ($selected) {
                            if ($article->getQuantitePrelevee() !== $article->getQuantiteAPrelever()) {
                                $insertedArticle->setQuantiteAPrelever($article->getQuantiteAPrelever() - $article->getQuantitePrelevee());
                                $articlesSplittedToKeep[] = $insertedArticle->getId();
                            }
                            $article->setQuantite($quantitePrelevee);
                        } else {
                            $preparation->addArticle($insertedArticle);
                            $preparation->removeArticle($article);
                            $articlesSplittedToKeep[] = $article->getId();
                        }
                    }
                    if ($selected) {
                        // création des mouvements de préparation pour les articles
                        $mouvement = new MouvementStock();
                        $mouvement
                            ->setUser($user)
                            ->setArticle($article)
                            ->setQuantity($quantitePrelevee)
                            ->setEmplacementFrom($article->getEmplacement())
                            ->setType(MouvementStock::TYPE_TRANSFER)
                            ->setPreparationOrder($preparation);
                        $entityManager->persist($mouvement);
                    }
                }
                else {
                    throw new NegativeQuantityException($article);
                }
            }
        }

        // création des mouvements de préparation pour les articles de référence
        foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
            $articleRef = $ligneArticle->getReference();
            $mouvementAlreadySaved = $preparation->getReferenceArticleMovement($articleRef);
            if (!$mouvementAlreadySaved && !empty($ligneArticle->getQuantitePrelevee())) {
                if ($articleRef->getQuantiteStock() >= $ligneArticle->getQuantitePrelevee()) {
                    $mouvement = new MouvementStock();
                    $mouvement
                        ->setUser($user)
                        ->setRefArticle($articleRef)
                        ->setQuantity($ligneArticle->getQuantitePrelevee())
                        ->setEmplacementFrom($articleRef->getEmplacement())
                        ->setType(MouvementStock::TYPE_TRANSFER)
                        ->setPreparationOrder($preparation);
                    $entityManager->persist($mouvement);
                }
                else {
                    throw new NegativeQuantityException($articleRef);
                }
            }
        }

        $entityManager->flush();

        if (!$preparation->getStatut() || !$preparation->getUtilisateur()) {
            // modif du statut de la préparation
            $statutEDP = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
            $preparation
                ->setStatut($statutEDP)
                ->setUtilisateur($user);
            $entityManager->flush();
        }
        return $articlesSplittedToKeep;
    }

    /**
     * @param array|null $params
     * @return array
     * @throws Exception
     */
    public function getDataForDatatable($params = null, $filterDemande = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $preparationRepository = $this->entityManager->getRepository(Preparation::class);

        if ($filterDemande) {
            $filters = [
                [
                    'field' => FiltreSup::FIELD_DEMANDE,
                    'value' => $filterDemande
                ]
            ];
        }
        else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PREPA, $this->security->getUser());
        }

        $queryResult = $preparationRepository->findByParamsAndFilters($params, $filters);

        $preparations = $queryResult['data'];

        $rows = [];
        foreach ($preparations as $preparation) {
            $rows[] = $this->dataRowPreparation($preparation);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param Preparation $preparation
     * @param EntityManagerInterface|null $entityManager
     */
    public function updateRefArticlesQuantities(Preparation $preparation,
                                                EntityManagerInterface $entityManager = null) {
        foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
            $refArticle = $ligneArticle->getReference();
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $this->refArticleDataService->updateRefArticleQuantities($refArticle);
            }
            // On ne touche pas aux références gérées par article : décrémentation du stock à la fin de la livraison
        }

        if (!isset($entityManager)) {
            $entityManager = $this->entityManager;
        }

        $entityManager->flush();
    }

    /**
     * @param Preparation $preparation
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function dataRowPreparation($preparation)
    {
        $request = $preparation->getDemande();

        return [
            'Numéro' => $preparation->getNumero() ?? '',
            'Date' => $preparation->getDate() ? $preparation->getDate()->format('d/m/Y') : '',
            'Opérateur' => $preparation->getUtilisateur() ? $preparation->getUtilisateur()->getUsername() : '',
            'Statut' => $preparation->getStatut() ? $preparation->getStatut()->getNom() : '',
            'Type' => $request && $request->getType() ? $request->getType()->getLabel() : '',
            'Actions' => $this->templating->render('preparation/datatablePreparationRow.html.twig', [
                "url" => $this->router->generate('preparation_show', ["id" => $preparation->getId()])
            ]),
        ];
    }


    /**
     * @param Preparation $preparation
     * @param EntityManagerInterface $entityManager
     * @throws NonUniqueResultException
     */
    public function resetPreparationToTreat(Preparation $preparation,
                                            EntityManagerInterface $entityManager): void {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $statutP = $statutRepository->findOneByCategorieNameAndStatutCode(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);

        $movements = $preparation->getMouvements()->toArray();
        /** @var MouvementStock $movement */
        foreach ($movements as $movement) {
            $movement->setPreparationOrder(null);
        }

        $preparation->setStatut($statutP);
        $preparation->getMouvements()->clear();

        foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
            $ligneArticle->setQuantitePrelevee(0);
        }

        foreach ($preparation->getArticles() as $article) {
            $article->setQuantitePrelevee(0);
        }
    }

    public function generateNumber(DateTime $date, EntityManagerInterface $entityManager): string {
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $preparationNumber = ('P-' . $date->format('YmdHis'));
        $preparationWithSameNumber = $preparationRepository->countByNumero($preparationNumber);
        $preparationWithSameNumber++;

        $currentCounterStr = $preparationWithSameNumber < 10
            ? ('0' . $preparationWithSameNumber)
            : $preparationWithSameNumber;

        return ($preparationNumber . '-' . $currentCounterStr);
    }

    /**
     * @param Preparation $preparation
     * @param EntityManagerInterface $entityManager
     * @return ReferenceArticle[]
     */
    public function managePreRemovePreparation(Preparation $preparation, EntityManagerInterface $entityManager): array {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $demande = $preparation->getDemande();

        $requestStatusDraft = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
        $statutActifArticle = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);

        if ($demande->getPreparations()->count() === 1) {
            $demande
                ->setStatut($requestStatusDraft);
        }

        foreach ($preparation->getArticles() as $article) {
            $article->setPreparation(null);
            $article->setStatut($statutActifArticle);
            $article->setQuantitePrelevee(0);
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
            } else {
                $refToUpdate[] = $refArticle;
            }
            $entityManager->remove($ligneArticlePreparation);
        }
        return $refToUpdate;
    }
}
