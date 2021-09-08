<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorWrapper;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Exceptions\NegativeQuantityException;
use App\Repository\ArticleRepository;
use App\Repository\StatutRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;


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
    private $CSVExportService;

    /** @Required */
    public NotificationService $notificationService;

    public function __construct(Security $security,
                                CSVExportService $CSVExportService,
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
        $this->CSVExportService = $CSVExportService;
        $this->refMouvementsToRemove = [];
    }

    public function setEntityManager(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        return $this;
    }

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
        foreach ($preparation->getArticleLines() as $article) {
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

        $articles = $preparation->getArticleLines();
        foreach ($articles as $article) {
            if (($article->getQuantitePrelevee() < $article->getQuantiteAPrelever()) || empty($article->getQuantitePrelevee())) {
                $complete = false;
                break;
            }
        }

        if ($complete) {
            $lignesArticle = $preparation->getReferenceLines();

            foreach ($lignesArticle as $ligneArticle) {
                if ($ligneArticle->getPickedQuantity() < $ligneArticle->getQuantity()) {
                    $complete = false;
                    break;
                }
            }
        }

        return $complete;
    }

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
        $date = new DateTime('now');
        $number = $this->generateNumber($date, $entityManager);
        $newPreparation
            ->setNumero($number)
            ->setDate($date)
            ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_A_TRAITER));

        $demande->addPreparation($newPreparation);
        foreach ($listOfArticleSplitted as $articleId) {
            /** @var Article $articleToKeep */
            $articleToKeep = $articleRepository->find($articleId);
            $newPreparation->addArticleLine($articleToKeep);
            $demande->addArticle($articleToKeep);
        }

        foreach ($preparation->getReferenceLines() as $ligneArticlePreparation) {
            $refArticle = $ligneArticlePreparation->getReference();
            $pickedQuantity = $ligneArticlePreparation->getPickedQuantity();
            if ($ligneArticlePreparation->getQuantity() !== $pickedQuantity) {
                $newLigneArticle = new PreparationOrderReferenceLine();
                $selectedQuantityForPreviousLigne = $ligneArticlePreparation->getPickedQuantity() ?? 0;
                $newQuantity = ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE)
                    ? ($ligneArticlePreparation->getQuantity() - $selectedQuantityForPreviousLigne)
                    : $ligneArticlePreparation->getQuantity();
                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $ligneArticlePreparation->setQuantity($ligneArticlePreparation->getPickedQuantity() ?? 0);
                }
                $newLigneArticle
                    ->setPreparation($newPreparation)
                    ->setReference($refArticle)
                    ->setQuantity($newQuantity);

                if (empty($pickedQuantity)) {
                    $entityManager->remove($ligneArticlePreparation);
                }

                $entityManager->persist($newLigneArticle);
            }
        }

        $entityManager->persist($newPreparation);
        $entityManager->flush();

        if ($newPreparation->getDemande()->getType()->isNotificationsEnabled()) {
            $this->notificationService->toTreat($newPreparation);
        }

        return $newPreparation;
    }

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
                : $referenceArticleRepository->findOneBy(['reference' => $article]);
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

    public function deleteLigneRefOrNot(?PreparationOrderReferenceLine $ligne)
    {
        if ($ligne && $ligne->getQuantity() === 0) {
            $this->entityManager->remove($ligne);
        }
    }

    public function treatMouvementQuantities($mouvement, Preparation $preparation)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $referenceLineRepository = $this->entityManager->getRepository(PreparationOrderReferenceLine::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        if ($mouvement['is_ref']) {
            // cas ref par ref
            $refArticle = $referenceArticleRepository->findOneBy(['reference' => $mouvement['reference']]);
            if ($refArticle) {
                $referenceLine = $referenceLineRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                $referenceLine->setPickedQuantity($mouvement['quantity']);
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
                        $referenceLine = $referenceLineRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                        $this->treatArticleSplitting($article, $mouvement['quantity'], $referenceLine);
                    }
                }

                $article
                    ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT))
                    ->setQuantitePrelevee($mouvement['quantity']);
            }
        }

        $this->entityManager->flush();
    }

    public function treatArticleSplitting(Article                       $article,
                                          int                           $quantite,
                                          PreparationOrderReferenceLine $ligneArticle,
                                          ?Statut                       $statusArticle = null)
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
            $ligneArticle->setQuantity($ligneArticle->getQuantity() + ($article->getQuantitePrelevee() - $quantite));
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

    public function createMouvementsPrepaAndSplit(Preparation $preparation,
                                                  Utilisateur $user,
                                                  EntityManagerInterface $entityManager): array
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $articlesSplittedToKeep = [];

        $articles = $preparation->getArticleLines();
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
                            $preparation->addArticleLine($insertedArticle);
                            $preparation->removeArticleLine($article);
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
        foreach ($preparation->getReferenceLines() as $ligneArticle) {
            $articleRef = $ligneArticle->getReference();
            $mouvementAlreadySaved = $preparation->getReferenceArticleMovement($articleRef);
            if (!$mouvementAlreadySaved && !empty($ligneArticle->getPickedQuantity())) {
                if ($articleRef->getQuantiteStock() >= $ligneArticle->getPickedQuantity()) {
                    $mouvement = new MouvementStock();
                    $mouvement
                        ->setUser($user)
                        ->setRefArticle($articleRef)
                        ->setQuantity($ligneArticle->getPickedQuantity())
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

    public function updateRefArticlesQuantities(Preparation $preparation,
                                                EntityManagerInterface $entityManager = null) {

        if (!isset($entityManager)) {
            $entityManager = $this->entityManager;
        }

        foreach ($preparation->getReferenceLines() as $ligneArticle) {
            $refArticle = $ligneArticle->getReference();
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $this->refArticleDataService->updateRefArticleQuantities($entityManager, $refArticle);
            }
            // On ne touche pas aux références gérées par article : décrémentation du stock à la fin de la livraison
        }

        $entityManager->flush();
    }

    private function dataRowPreparation(Preparation $preparation)
    {
        $lastMessage = $preparation->getLastMessage();
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper()) ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName() : null;
        $hasPairing = !$preparation->getPairings()->isEmpty();

        $request = $preparation->getDemande();
        return [
            'Numéro' => $preparation->getNumero() ?? '',
            'Date' => $preparation->getDate() ? $preparation->getDate()->format('d/m/Y') : '',
            'Opérateur' => $preparation->getUtilisateur() ? $preparation->getUtilisateur()->getUsername() : '',
            'Statut' => $preparation->getStatut() ? $preparation->getStatut()->getNom() : '',
            'Type' => $request && $request->getType() ? $request->getType()->getLabel() : '',
            'Actions' => $this->templating->render('preparation/datatablePreparationRow.html.twig', [
                "url" => $this->router->generate('preparation_show', ["id" => $preparation->getId()]),
                'titleLogo' => !$preparation->getPairings()->isEmpty() ? 'pairing' : null
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing,
            ]),
        ];
    }

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

        foreach ($preparation->getReferenceLines() as $ligneArticle) {
            $ligneArticle->setPickedQuantity(0);
        }

        foreach ($preparation->getArticleLines() as $article) {
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

    public function managePreRemovePreparation(Preparation $preparation, EntityManagerInterface $entityManager): array {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $demande = $preparation->getDemande();

        $requestStatusDraft = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
        $statutActifArticle = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);

        if ($demande->getPreparations()->count() === 1) {
            $demande
                ->setStatut($requestStatusDraft);
        }

        foreach ($preparation->getArticleLines() as $article) {
            $article->setPreparation(null);
            $article->setStatut($statutActifArticle);
            $article->setQuantitePrelevee(0);
        }

        $refToUpdate = [];

        foreach ($preparation->getReferenceLines() as $ligneArticlePreparation) {
            $refArticle = $ligneArticlePreparation->getReference();
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $quantiteReservee = $refArticle->getQuantiteReservee();
                $quantiteAPrelever = $ligneArticlePreparation->getQuantity();
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

    public function putPreparationLines($handle, Preparation $preparation): void {
        $preparationBaseData = $preparation->serialize();

        foreach ($preparation->getReferenceLines() as $ligneArticle) {
            $referenceArticle = $ligneArticle->getReference();

            $this->CSVExportService->putLine($handle, array_merge($preparationBaseData, [
                $referenceArticle->getReference() ?? '',
                $referenceArticle->getLibelle() ?? '',
                $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
                $ligneArticle->getQuantity() ?? 0,
                $referenceArticle->getBarCode()
            ]));
        }

        foreach ($preparation->getArticleLines() as $article) {
            $articleFournisseur = $article->getArticleFournisseur();
            $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
            $reference = $referenceArticle ? $referenceArticle->getReference() : '';

            $this->CSVExportService->putLine($handle, array_merge($preparationBaseData, [
                $reference,
                $article->getLabel() ?? '',
                $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                $article->getQuantite() ?? 0,
                $article->getBarCode()
            ]));
        }
    }

    public function createPairing(SensorWrapper $sensorWrapper, Preparation $preparation){
        $pairing = new Pairing();
        $start =  new DateTime("now");
        $pairing
            ->setStart($start)
            ->setSensorWrapper($sensorWrapper)
            ->setPreparationOrder($preparation)
            ->setActive(true);

        return $pairing;
    }
}
