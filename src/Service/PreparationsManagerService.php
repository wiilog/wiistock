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
use App\Repository\DemandeRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\PreparationRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ObjectRepository;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;


/**
 * Class PreparationsManagerService
 * @package App\Service
 */
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
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    public function __construct(DemandeRepository $demandeRepository,
                                FiltreSupRepository $filtreSupRepository,
                                Security $security,
                                PreparationRepository $preparationRepository,
                                RouterInterface $router,
                                Twig_Environment $templating,
                                ArticleDataService $articleDataService,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager)
    {
        $this->demandeRepository = $demandeRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->security = $security;
        $this->preparationRepository = $preparationRepository;
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
     * @param Emplacement $emplacement
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
     * @return Preparation|null
     * @throws Exception
     */
    public function treatPreparation(Preparation $preparation, $userNomade, Emplacement $emplacement, array $articlesToKeep): ?Preparation
    {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
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
            ->setStatut($statutPreparePreparation);

        // TODO get remaining articles and refs
        if (!$isPreparationComplete) {
            return $this->persistPreparationFromOldOne($preparation, $demande, $statutRepository, $articleRepository, $articlesToKeep);
        } else {
            return null;
        }
    }

    private function isPreparationComplete(Preparation $preparation)
    {
        $complete = true;

        $articles = $preparation->getArticles();
        foreach ($articles as $article) {
            if ($article->getQuantitePrelevee() < $article->getQuantiteAPrelever()) $complete = false;
        }

        $lignesArticle = $preparation->getLigneArticlePreparations();
        foreach ($lignesArticle as $ligneArticle) {
            if ($ligneArticle->getQuantitePrelevee() < $ligneArticle->getQuantite()) $complete = false;
        }

        return $complete;
    }

    /**
     * @param DateTime $dateEnd
     * @param Preparation $preparation
     * @return Livraison
     * @throws NonUniqueResultException
     */
    public function persistLivraison(DateTime $dateEnd, Preparation $preparation)
    {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_A_TRAITER);
        $livraison = new Livraison();

        $livraison
            ->setPreparation($preparation)
            ->setDate($dateEnd)
            ->setNumero('L-' . $dateEnd->format('YmdHis'))
            ->setStatut($statut);

        $this->entityManager->persist($livraison);

        return $livraison;
    }


    /**
     * @param Preparation $preparation
     * @param Demande $demande
     * @param ObjectRepository $statutRepository
     * @param ObjectRepository $articleRepository
     * @param array $listOfArticleSplitted
     * @return Preparation
     * @throws Exception
     */
    private function persistPreparationFromOldOne(Preparation $preparation,
                                                  Demande $demande,
                                                  ObjectRepository $statutRepository,
                                                  ObjectRepository $articleRepository,
                                                  array $listOfArticleSplitted): Preparation {
        $newPreparation = new Preparation();
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $newPreparation
            ->setNumero('P-' . $date->format('YmdHis'))
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
            if ($ligneArticlePreparation->getQuantite() !== $ligneArticlePreparation->getQuantitePrelevee()) {
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
                $this->entityManager->persist($newLigneArticle);
            }
        }

        $this->entityManager->persist($newPreparation);
        $this->entityManager->flush();

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
     * @throws NonUniqueResultException
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
                $mouvement
                    ->setRefArticle($refArticle)
                    ->setQuantity($mouvementRepository->findOneByRefAndPrepa($refArticle->getId(), $preparation->getId())->getQuantity());
            }
        } else {
            $article = ($article instanceof Article)
                ? $article
                : $articleRepository->findOneByReference($article);
            if ($article) {
                // si c'est un article sélectionné par l'utilisateur :
                // on prend la quantité donnée dans le mouvement
                // sinon on prend la quantité spécifiée dans le mouvement de transfert (créé dans beginPrepa)
                $mouvementQuantity = ($isSelectedByArticle
                    ? $quantity
                    : $mouvementRepository->findByArtAndPrepa($article->getId(), $preparation->getId())->getQuantity());

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
                        // et si ça n'a pas déjà été fait, on supprime le lien entre la réf article et la demande
                    }
                }

                $article
                    ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT))
                    ->setQuantitePrelevee($mouvement['quantity']);
            }
        }

        $this->entityManager->flush();
    }

    public function treatArticleSplitting(Article $article, int $quantite, LigneArticlePreparation $ligneArticle)
    {
        if ($quantite !== '' && $quantite > 0 && $quantite <= $article->getQuantite()) {
            if (!$article->getPreparation()) {
                $article->setQuantiteAPrelever(0);
                $article->setQuantitePrelevee(0);
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
     * @return array
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function createMouvementsPrepaAndSplit(Preparation $preparation, Utilisateur $user): array
    {
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $articlesSplittedToKeep = [];
        // modification des articles de la demande
        $articles = $preparation->getArticles();
        foreach ($articles as $article) {
            $mouvementAlreadySaved = $mouvementRepository->findByArtAndPrepa($article->getId(), $preparation->getId());
            if (!$mouvementAlreadySaved) {
                $quantitePrelevee = $article->getQuantitePrelevee();
                $selected = !(empty($quantitePrelevee));
                $article->setStatut(
                    $statutRepository->findOneByCategorieNameAndStatutCode(
                        Article::CATEGORIE,
                        $selected ? Article::STATUT_EN_TRANSIT : Article::STATUT_ACTIF));
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

                    foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
                        $newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
                    }
                    $insertedArticle = $this->articleDataService->newArticle($newArticle);
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
                        ->setType(MouvementStock::TYPE_TRANSFERT)
                        ->setPreparationOrder($preparation);
                    $this->entityManager->persist($mouvement);
                }
                $this->entityManager->flush();
            }
        }

        // création des mouvements de préparation pour les articles de référence
        foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
            $articleRef = $ligneArticle->getReference();
            $mouvementAlreadySaved = $mouvementRepository->findOneByRefAndPrepa($articleRef->getId(), $preparation->getId());
            if (!$mouvementAlreadySaved && !empty($ligneArticle->getQuantitePrelevee())) {
                $mouvement = new MouvementStock();
                $mouvement
                    ->setUser($user)
                    ->setRefArticle($articleRef)
                    ->setQuantity($ligneArticle->getQuantitePrelevee())
                    ->setEmplacementFrom($articleRef->getEmplacement())
                    ->setType(MouvementStock::TYPE_TRANSFERT)
                    ->setPreparationOrder($preparation);
                $this->entityManager->persist($mouvement);
            }
            $this->entityManager->flush();
        }

        if (!$preparation->getStatut() || !$preparation->getUtilisateur()) {
            // modif du statut de la préparation
            $statutEDP = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
            $preparation
                ->setStatut($statutEDP)
                ->setUtilisateur($user);
            $this->entityManager->flush();
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
        if ($filterDemande) {
            $filters = [
                [
                    'field' => FiltreSup::FIELD_DEMANDE,
                    'value' => $filterDemande
                ]
            ];
        } else {
            $filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PREPA, $this->security->getUser());
        }

        $queryResult = $this->preparationRepository->findByParamsAndFilters($params, $filters);

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
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function updateRefArticlesQuantities(Preparation $preparation) {
        foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
            $refArticle = $ligneArticle->getReference();
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $this->refArticleDataService->updateRefArticleQuantities($refArticle);
            }
            else if($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $quantitePicked = $ligneArticle->getQuantitePrelevee();
                $newQuantiteStock = (($refArticle->getQuantiteStock() ?? 0) - $quantitePicked);
                $newQuantiteReservee = (($refArticle->getQuantiteReservee() ?? 0) - $quantitePicked);
                $refArticle->setQuantiteStock($newQuantiteStock > 0 ? $newQuantiteStock : 0);
                $refArticle->setQuantiteReservee($newQuantiteReservee > 0 ? $newQuantiteReservee : 0);
            }
        }

        $this->entityManager->flush();
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
        $demande = $preparation->getDemande();
        $url['show'] = $this->router->generate('preparation_show', ['id' => $preparation->getId()]);
        $row = [
            'Numéro' => $preparation->getNumero() ?? '',
            'Date' => $preparation->getDate() ? $preparation->getDate()->format('d/m/Y') : '',
            'Opérateur' => $preparation->getUtilisateur() ? $preparation->getUtilisateur()->getUsername() : '',
            'Statut' => $preparation->getStatut() ? $preparation->getStatut()->getNom() : '',
            'Type' => $demande && $demande->getType() ? $demande->getType()->getLabel() : '',
            'Actions' => $this->templating->render('preparation/datatablePreparationRow.html.twig', ['url' => $url]),
        ];

        return $row;
    }

}
