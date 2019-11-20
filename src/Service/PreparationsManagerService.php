<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\LigneArticle;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;


/**
 * Class PreparationsManagerService
 * @package App\Service
 */
class PreparationsManagerService {

    public const MOUVEMENT_DOES_NOT_EXIST_EXCEPTION = 'mouvement-does-not-exist';
    public const ARTICLE_ALREADY_SELECTED = 'article-already-selected';

    private $entityManager;
    private $articleDataService;

    /**
     * @var array
     */
    private $refMouvementsToRemove;

    public function __construct(ArticleDataService $articleDataService,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->articleDataService = $articleDataService;
        $this->refMouvementsToRemove = [];
    }

    public function setEntityManager(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * On termine les mouvements de prepa
     * @param Preparation $preparation
     * @param DateTime $date
     * @param Emplacement $emplacement
     */
    public function closePreparationMouvement(Preparation $preparation, DateTime $date, Emplacement $emplacement = null): void {
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
     * @param Livraison $livraison
     * @param $userNomade
     * @throws NonUniqueResultException
     */
    public function treatPreparation(Preparation $preparation, Livraison $livraison, $userNomade): void {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $statutPrepareDemande = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_PREPARE);
        $statutPreparePreparation = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::PREPARATION, Preparation::STATUT_PREPARE);

        $demandes = $preparation->getDemandes();
        $demande = $demandes[0];

        $livraison->addDemande($demande);

        $preparation
            ->addLivraison($livraison)
            ->setUtilisateur($userNomade)
            ->setStatut($statutPreparePreparation);

        $demande->setStatut($statutPrepareDemande);
    }

    /**
     * @param DateTime $dateEnd
     * @return Livraison
     * @throws NonUniqueResultException
     */
    public function persistLivraison(DateTime $dateEnd) {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $statut = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_A_TRAITER);
        $livraison = new Livraison();

        $livraison
            ->setDate($dateEnd)
            ->setNumero('L-' . $dateEnd->format('YmdHis'))
            ->setStatut($statut);

        $this->entityManager->persist($livraison);

        return $livraison;
    }

    /**
     * @param int $quantity
     * @param Preparation $preparation
     * @param Utilisateur $userNomade
     * @param Livraison $livraison
     * @param bool $isRef
     * @param $article
     * @param Emplacement|null $emplacementFrom
     * @param bool $isSelectedByArticle
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function treatMouvement(int $quantity,
                                   Preparation $preparation,
                                   Utilisateur $userNomade,
                                   Livraison $livraison,
                                   bool $isRef,
                                   $article,
                                   Emplacement $emplacementFrom = null,
                                   bool $isSelectedByArticle = false) {
        //repositories
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);
        $ligneArticleRepository = $this->entityManager->getRepository(LigneArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $mouvement = new MouvementStock();
        $mouvement
            ->setUser($userNomade)
            ->setQuantity($quantity)
            ->setType(MouvementStock::TYPE_SORTIE)
            ->setLivraisonOrder($livraison)
            ->setExpectedDate($livraison->getDate());

        if (isset($emplacementFrom)) {
            $mouvement->setEmplacementFrom($emplacementFrom);
        }

        $this->entityManager->persist($mouvement);

        if ($isRef) {
            $refArticle = ($article instanceof ReferenceArticle)
                ? $article
                : $referenceArticleRepository->findOneByReference($article);
            if ($refArticle) {
                $mouvement->setRefArticle($refArticle);
                $mouvement->setQuantity($mouvementRepository->findByRefAndPrepa($refArticle->getId(), $preparation->getId())->getQuantity());
                $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $livraison->getPreparation()->getDemandes()[0]);
                $ligneArticle->setQuantite($mouvement->getQuantity());
            }
        }
        else {
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

                $mouvement->setQuantity($mouvementQuantity);
                $article->setStatut($statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT));
                $mouvement->setArticle($article);
                $article->setQuantiteAPrelever($mouvement->getQuantity());

                if ($article->getQuantite() !== $article->getQuantiteAPrelever()) {
                    $newArticle = [
                        'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                        'libelle' => $article->getLabel(),
                        'conform' => !$article->getConform(),
                        'commentaire' => $article->getcommentaire(),
                        'quantite' => $article->getQuantite() - $article->getQuantiteAPrelever(),
                        'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                        'statut' => Article::STATUT_ACTIF,
                        'prix' => $article->getPrixUnitaire(),
                        'refArticle' => $article->getArticleFournisseur()->getReferenceArticle()->getId()
                    ];

                    foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
                        $newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
                    }
                    $this->articleDataService->newArticle($newArticle);

                    $article->setQuantite($article->getQuantiteAPrelever());
                }

                if ($isSelectedByArticle) {
                    if ($article->getDemande()) {
                        throw new Exception(self::ARTICLE_ALREADY_SELECTED);
                    } else {
                        // TODO AB gérer le fait qu'une livraison soit liée à plusieurs demande
                        // on crée le lien entre l'article et la demande
                        $demande = $livraison->getDemande()->getValues()[0];
                        $article->setDemande($demande);

                        // et si ça n'a pas déjà été fait, on supprime le lien entre la réf article et la demande
                        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                        $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $demande);
                        if (!empty($ligneArticle)) {
                            $this->entityManager->remove($ligneArticle);
                        }

                        // on crée le mouvement de transfert de l'article
                        $mouvementRef = $mouvementRepository->findByRefAndPrepa($refArticle, $preparation);
                        $newMouvement = new MouvementStock();
                        $newMouvement
                            ->setUser($userNomade)
                            ->setArticle($article)
                            ->setQuantity($article->getQuantiteAPrelever())
                            ->setEmplacementFrom($article->getEmplacement())
                            ->setEmplacementTo($mouvementRef ? $mouvementRef->getEmplacementTo() : '')
                            ->setType(MouvementStock::TYPE_TRANSFERT)
                            ->setPreparationOrder($preparation)
                            ->setDate($mouvementRef ? $mouvementRef->getDate() : '')
                            ->setExpectedDate($preparation->getDate());
                        $this->entityManager->persist($newMouvement);
                        if ($mouvementRef) {
                            $this->refMouvementsToRemove[] = $mouvementRef;
                        }
                    }
                }
            }
        }
    }

    /**
     * On supprime les mouvements de transfert créés pour les réf gérées à l'articles
     * (elles ont été remplacées plus haut par les mouvements de transfert des articles)
     */
    public function removeRefMouvements(): void {
        foreach ($this->refMouvementsToRemove as $mvtToRemove){
            $this->entityManager->remove($mvtToRemove);
        }
        $this->refMouvementsToRemove = [];
    }

    /**
     * @param Preparation $preparation
     * @param Utilisateur $user
     * @throws NonUniqueResultException
     */
    public function createMouvementAndScission(Preparation $preparation, Utilisateur $user) {
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $demandes = $preparation->getDemandes();
        $demande = $demandes[0];

        // modification des articles de la demande
        $articles = $demande->getArticles();
        foreach ($articles as $article) {
            $mouvementAlreadySaved = $mouvementRepository->findByArtAndPrepa($article->getId(), $preparation->getId());
            if (!$mouvementAlreadySaved) {
                $article->setStatut($statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
                // scission des articles dont la quantité prélevée n'est pas totale
                if ($article->getQuantite() !== $article->getQuantiteAPrelever()) {
                    $newArticle = [
                        'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                        'libelle' => $article->getLabel(),
                        'prix' => $article->getPrixUnitaire(),
                        'conform' => !$article->getConform(),
                        'commentaire' => $article->getcommentaire(),
                        'quantite' => $article->getQuantite() - $article->getQuantiteAPrelever(),
                        'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                        'statut' => Article::STATUT_ACTIF,
                        'refArticle' => $article->getArticleFournisseur()->getReferenceArticle()->getId()
                    ];

                    foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
                        $newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
                    }
                    $this->articleDataService->newArticle($newArticle);

                    $article->setQuantite($article->getQuantiteAPrelever());
                }

                // création des mouvements de préparation pour les articles
                $mouvement = new MouvementStock();
                $mouvement
                    ->setUser($user)
                    ->setArticle($article)
                    ->setQuantity($article->getQuantiteAPrelever())
                    ->setEmplacementFrom($article->getEmplacement())
                    ->setType(MouvementStock::TYPE_TRANSFERT)
                    ->setPreparationOrder($preparation)
                    ->setExpectedDate($preparation->getDate());
                $this->entityManager->persist($mouvement);
                $this->entityManager->flush();
            }
        }

        // création des mouvements de préparation pour les articles de référence
        foreach ($demande->getLigneArticle() as $ligneArticle) {
            $articleRef = $ligneArticle->getReference();

            $mouvementAlreadySaved = $mouvementRepository->findByRefAndPrepa($articleRef->getId(), $preparation->getId());
            if (!$mouvementAlreadySaved) {
                $mouvement = new MouvementStock();
                $mouvement
                    ->setUser($user)
                    ->setRefArticle($articleRef)
                    ->setQuantity($ligneArticle->getQuantite())
                    ->setEmplacementFrom($articleRef->getEmplacement())
                    ->setType(MouvementStock::TYPE_TRANSFERT)
                    ->setPreparationOrder($preparation)
                    ->setExpectedDate($preparation->getDate());
                $this->entityManager->persist($mouvement);
                $this->entityManager->flush();
            }
        }

        if (!$preparation->getStatut() || !$preparation->getUtilisateur()) {
            // modif du statut de la préparation
            $statutEDP = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
            $preparation
                ->setStatut($statutEDP)
                ->setUtilisateur($user);
            $this->entityManager->flush();
        }
    }

}
