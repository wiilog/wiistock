<?php

namespace App\Service\Nomade;

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
use App\Service\ArticleDataService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;


/**
 * Class PreparationsManagerService
 * @package App\Service\Nomade
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

    /**
     * On termine les mouvements de prepa
     * @param Preparation $preparation
     * @param string $emplacement
     * @param DateTime $date
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function closePreparationMouvement(Preparation $preparation, string $emplacement, DateTime $date): void {
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);

        $mouvements = $mouvementRepository->findByPreparation($preparation);
        $emplacementPrepa = $emplacementRepository->findOneByLabel($emplacement);
        foreach ($mouvements as $mouvement) {
            if ($emplacementPrepa) {
                $mouvement
                    ->setDate($date)
                    ->setEmplacementTo($emplacementPrepa);
            } else {
                throw new Exception(self::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
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

        $demandes = $preparation->getDemandes();
        $demande = $demandes[0];

        $livraison->addDemande($demande);

        $preparation
            ->addLivraison($livraison)
            ->setUtilisateur($userNomade)
            ->setStatut($statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::PREPARATION, Preparation::STATUT_PREPARE));

        $demande
            ->setStatut($statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_PREPARE))
            ->setLivraison($livraison);
    }

    /**
     * @param array $preparationArray
     * @return Livraison
     * @throws NonUniqueResultException
     */
    public function persistLivraison(array $preparationArray) {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $statut = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_A_TRAITER);
        $livraison = new Livraison();

        $date = DateTime::createFromFormat(DateTime::ATOM, $preparationArray['date_end']);
        $livraison
            ->setDate($date)
            ->setNumero('L-' . $date->format('YmdHis'))
            ->setStatut($statut);
        $this->entityManager->persist($livraison);
        return $livraison;
    }

    /**
     * @param $mouvementNomade
     * @param $userNomade
     * @param Livraison $livraison
     * @throws NonUniqueResultException
     */
    public function treatMouvement($mouvementNomade, $userNomade, Livraison $livraison) {
        //repositories
        $preparationRepository = $this->entityManager->getRepository(Preparation::class);
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);
        $ligneArticleRepository = $this->entityManager->getRepository(LigneArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $preparation = $preparationRepository->find($mouvementNomade['id_prepa']);
        $emplacement = $emplacementRepository->findOneByLabel($mouvementNomade['location']);
        $mouvement = new MouvementStock();
        $mouvement
            ->setUser($userNomade)
            ->setQuantity($mouvementNomade['quantity'])
            ->setEmplacementFrom($emplacement)
            ->setType(MouvementStock::TYPE_SORTIE)
            ->setLivraisonOrder($livraison)
            ->setExpectedDate($livraison->getDate());
        $this->entityManager->persist($mouvement);

        if ($mouvementNomade['is_ref']) {
            $refArticle = $referenceArticleRepository->findOneByReference($mouvementNomade['reference']);
            if ($refArticle) {
                $mouvement->setRefArticle($refArticle);
                $mouvement->setQuantity($mouvementRepository->findByRefAndPrepa($refArticle->getId(), $preparation->getId())->getQuantity());
                $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $livraison->getPreparation()->getDemandes()[0]);
                $ligneArticle->setQuantite($mouvement->getQuantity());
            }
        }
        else {
            $article = $articleRepository->findOneByReference($mouvementNomade['reference']);
            if ($article) {
                $isSelectedByArticle = (
                    isset($mouvementNomade['selected_by_article']) &&
                    $mouvementNomade['selected_by_article']
                );

                // si c'est un article sélectionné par l'utilisateur :
                // on prend la quantité donnée dans le mouvement
                // sinon on prend la quantité spécifiée dans le mouvement de transfert (créé dans beginPrepa)
                $mouvementQuantity = ($isSelectedByArticle
                    ? $mouvementNomade['quantity']
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
                        throw new \Exception(self::ARTICLE_ALREADY_SELECTED);
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

    public function beginPrepa(Preparation $preparation, Utilisateur $nomadUser): bool {
        if ($preparation->getStatut()->getNom() == Preparation::STATUT_A_TRAITER || $preparation->getUtilisateur() === $nomadUser) {
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
                            'refArticle' => isset($data['refArticle']) ? $data['refArticle'] : $article->getArticleFournisseur()->getReferenceArticle()->getId()
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
                        ->setUser($nomadUser)
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
                        ->setUser($nomadUser)
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
                    ->setUtilisateur($nomadUser);
                $this->entityManager->flush();
            }
            $done = true;
        }
        else {
            $done = false;
        }
        return $done;
    }

}
