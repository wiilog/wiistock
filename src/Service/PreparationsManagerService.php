<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Exceptions\NegativeQuantityException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;


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

    #[Required]
    public NotificationService $notificationService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public MouvementStockService $stockMovementService;



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

    public function closePreparationMovements(Preparation $preparation, DateTime $date, Emplacement $location = null): void
    {
        $movements = $preparation->getMouvements()->toArray();

        foreach ($movements as $movement) {
            if (!$movement->getEmplacementTo()) {
                $this->stockMovementService->finishStockMovement($movement, $date, $location);
            }
        }
    }

    public function handlePreparationTreatMovements(EntityManagerInterface $entityManager,
                                                    Preparation            $preparation,
                                                    Livraison              $livraison,
                                                    ?Emplacement           $locationEndPrepa,
                                                    Utilisateur            $user): void {
        $now = new DateTime('now');
        $ulToMove = [];
        $stockMovements = $preparation->getMouvements();
        $articles = ($stockMovements->count()
            ? Stream::from($stockMovements)
                ->map(fn(MouvementStock $mouvement) => [
                    'article' => $mouvement->getArticle() ?: $mouvement->getRefArticle(),
                    'quantity' => $mouvement->getQuantity(),
                    'movement' => $mouvement,
                ])
            : Stream::from(
                Stream::from($preparation->getArticleLines())
                    ->map(fn(PreparationOrderArticleLine $line) => [
                        'article' => $line->getArticle(),
                        'quantity' => $line->getPickedQuantity(),
                    ]),
                Stream::from($preparation->getReferenceLines())
                    ->map(fn(PreparationOrderReferenceLine $line) => [
                        'article' => $line->getReference(),
                        'quantity' => $line->getPickedQuantity(),
                    ])
                ))
            ->toArray();

        foreach ($articles as $article) {
            /** @var Article|ReferenceArticle $articleEntity */
            $articleEntity = $article['article'];
            $quantity = $article['quantity'];
            $movement = $article['movement'] ?? null;

            $isMovableElement = (
                $articleEntity
                && (
                    $articleEntity instanceof Article
                    || $articleEntity->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE
                )
            );

            if ($isMovableElement
                && (!$movement || $movement->getType() === MouvementStock::TYPE_TRANSFER)) {
                $this->persistDeliveryMovement(
                    $entityManager,
                    $quantity,
                    $user,
                    $livraison,
                    $articleEntity instanceof ReferenceArticle,
                    $articleEntity,
                    $preparation,
                    false,
                    $locationEndPrepa
                );

                $trackingMovementPick = $this->trackingMovementService->createTrackingMovement(
                    $articleEntity->getTrackingPack() ?: $articleEntity->getBarCode(),
                    $articleEntity->getEmplacement(),
                    $user,
                    $now,
                    false,
                    true,
                    TrackingMovement::TYPE_PRISE,
                    [
                        'preparation' => $preparation,
                        'mouvementStock' => $movement,
                        'entityManager' => $entityManager,
                    ]
                );
                $entityManager->persist($trackingMovementPick);

                if(!$articleEntity->getTrackingPack()){
                    $articleEntity->setTrackingPack($trackingMovementPick->getPack());
                }

                $trackingMovementDrop = $this->trackingMovementService->createTrackingMovement(
                    $trackingMovementPick->getPack(),
                    $locationEndPrepa,
                    $user,
                    $now,
                    false,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        'preparation' => $preparation,
                        'mouvementStock' => $movement,
                        'entityManager' => $entityManager,
                    ]
                );

                if ($articleEntity instanceof Article) {
                    $ulToMove[] = $articleEntity->getCurrentLogisticUnit();
                }

                $entityManager->persist($trackingMovementDrop);
            }
        }

        if (!empty($ulToMove)) {
            /** @var Pack $lu */
            foreach (array_unique($ulToMove) as $lu) {
                if ($lu != null) {
                    $lastLuDropLocation = $lu->getLastOngoingDrop()?->getEmplacement();
                    if (!$lastLuDropLocation) {
                        throw new FormException("L'unité logistique que vous souhaitez déplacer n'a pas d'emplacement initial. Vous devez déposer votre unité logistique sur un emplacement avant d'y déposer vos articles.");
                    }
                    $pickTrackingMovement = $this->trackingMovementService->createTrackingMovement(
                        $lu,
                        $lu->getLastOngoingDrop()->getEmplacement(),
                        $user,
                        $now,
                        false,
                        true,
                        TrackingMovement::TYPE_PRISE,
                        [
                            'preparation' => $preparation,
                            'entityManager' => $entityManager,
                        ]
                    );
                    $DropTrackingMovement = $this->trackingMovementService->createTrackingMovement(
                        $lu,
                        $locationEndPrepa,
                        $user,
                        $now,
                        false,
                        true,
                        TrackingMovement::TYPE_DEPOSE,
                        [
                            'preparation' => $preparation,
                            'entityManager' => $entityManager,
                        ]
                    );
                    $entityManager->persist($pickTrackingMovement);
                    $entityManager->persist($DropTrackingMovement);

                    $lu->setLastOngoingDrop($DropTrackingMovement)->setLastAction($DropTrackingMovement);
                }
            }
        }
    }

    public function treatPreparation(Preparation $preparation,
                                                 $user,
                                     Emplacement $emplacement,
                                     array       $options = []): ?Preparation
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $options["entityManager"] ?? $this->entityManager;
        $articleLinesToKeep = $options["articleLinesToKeep"] ?? [];
        $changeArticleLocation = $options["changeArticleLocation"] ?? true;

        $statutRepository = $entityManager->getRepository(Statut::class);

        $demande = $preparation->getDemande();

        if ($changeArticleLocation) {
            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($preparation->getArticleLines() as $articleLine) {
                $article = $articleLine->getArticle();
                if ($articleLine->getPickedQuantity() > 0) {
                    $article->setEmplacement($emplacement);
                }
            }
        }

        $isPreparationComplete = $this->isPreparationComplete($preparation);

        $prepaStatusLabel = $isPreparationComplete ? Preparation::STATUT_PREPARE : Preparation::STATUT_INCOMPLETE;
        $statutPreparePreparation = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, $prepaStatusLabel);
        $demandeStatusLabel = $isPreparationComplete ? Demande::STATUT_PREPARE : Demande::STATUT_INCOMPLETE;
        $statutPrepareDemande = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_LIVRAISON, $demandeStatusLabel);
        if ($demande->getStatut()?->getCode() === Demande::STATUT_A_TRAITER) {
            $demande->setStatut($statutPrepareDemande);
        }

        $preparation
            ->setUtilisateur($user)
            ->setStatut($statutPreparePreparation)
            ->setEndLocation($emplacement);

        // TODO get remaining articles and refs
        if (!$isPreparationComplete) {
            return $this->persistPreparationFromOldOne($entityManager, $preparation, $articleLinesToKeep);
        } else {
            return null;
        }
    }

    private function isPreparationComplete(Preparation $preparation)
    {
        $treatedReferenceLines = $preparation->getReferenceLines()
            ->filter(fn(PreparationOrderReferenceLine $line) => (
                !$line->getPickedQuantity()
                || $line->getPickedQuantity() < $line->getQuantityToPick()
            ))
            ->count();
        $treatedArticleLines = $preparation->getArticleLines()
            ->filter(fn(PreparationOrderArticleLine $line) => (
                !$line->getQuantityToPick()
                || !$line->getPickedQuantity()
                || $line->getPickedQuantity() < $line->getQuantityToPick()
            ))
            ->count();

        return (
            $treatedReferenceLines === 0
            && $treatedArticleLines === 0
        );
    }

    private function persistPreparationFromOldOne(EntityManagerInterface $entityManager,
                                                  Preparation $preparation,
                                                  array $listOfArticleSplitted): Preparation {


        $statutRepository = $entityManager->getRepository(Statut::class);
        $preparationOrderArticleLineRepository = $entityManager->getRepository(PreparationOrderArticleLine::class);

        $demande = $preparation->getDemande();

        $newPreparation = new Preparation();
        $date = new DateTime('now');
        $number = $this->generateNumber($date, $entityManager);
        $newPreparation
            ->setExpectedAt($demande->getExpectedAt())
            ->setNumero($number)
            ->setDate($date)
            ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_A_TRAITER));

        if(!$demande->getValidatedAt()) {
            $demande->setValidatedAt($date);
        }

        $demande->addPreparation($newPreparation);
        foreach ($listOfArticleSplitted as $lineId) {
            /** @var PreparationOrderArticleLine $line */
            $line = $preparationOrderArticleLineRepository->find($lineId);
            $newPreparation->addArticleLine($line);
            $preparation->removeArticleLine($line);
            $articleToKeep = $line->getArticle();
            $articleToKeep
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT));
        }

        foreach ($preparation->getReferenceLines() as $ligneArticlePreparation) {
            $refArticle = $ligneArticlePreparation->getReference();
            $pickedQuantity = $ligneArticlePreparation->getPickedQuantity();
            if ($ligneArticlePreparation->getQuantityToPick() !== $pickedQuantity) {
                $newLigneArticle = new PreparationOrderReferenceLine();
                $selectedQuantityForPreviousLigne = $ligneArticlePreparation->getPickedQuantity() ?? 0;
                $newQuantity = ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE)
                    ? ($ligneArticlePreparation->getQuantityToPick() - $selectedQuantityForPreviousLigne)
                    : $ligneArticlePreparation->getQuantityToPick();
                if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                    $ligneArticlePreparation->setQuantityToPick($ligneArticlePreparation->getPickedQuantity() ?? 0);
                }
                $newLigneArticle
                    ->setPreparation($newPreparation)
                    ->setReference($refArticle)
                    ->setQuantityToPick($newQuantity)
                    ->setDeliveryRequestReferenceLine($ligneArticlePreparation->getDeliveryRequestReferenceLine());
                if (empty($pickedQuantity)) {
                    $entityManager->remove($ligneArticlePreparation);
                }

                $entityManager->persist($newLigneArticle);
            }
        }

        $entityManager->persist($newPreparation);

        return $newPreparation;
    }

    public function persistDeliveryMovement(EntityManagerInterface   $entityManager,
                                            int                      $quantity,
                                            Utilisateur              $userNomade,
                                            Livraison                $livraison,
                                            bool                     $isRef,
                                            ReferenceArticle|Article $article,
                                            Preparation              $preparation,
                                            bool                     $isSelectedByArticle,
                                            Emplacement              $emplacementFrom = null): MouvementStock
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $now = new DateTime();
        if ($isRef) {
            $refArticle = $article instanceof ReferenceArticle
                ? $article
                : $referenceArticleRepository->findOneBy(['reference' => $article]);
            if ($refArticle) {
                $preparationMovement = $preparation->getReferenceArticleMovement($refArticle);
                $quantity = $preparationMovement?->getQuantity() ?: $quantity;
            }
        } else {
            $article = $article instanceof Article
                ? $article
                : $articleRepository->findOneByReference($article);
            if ($article) {
                $article->setInactiveSince($now);

                $preparationMovement = $preparation->getArticleMovement($article);

                $stockMovement = !$isSelectedByArticle
                    ? ($preparationMovement ?: null)
                    : null;
                // si c'est un article sélectionné par l'utilisateur :
                // on prend la quantité donnée dans le mouvement
                // sinon on prend la quantité spécifiée dans le mouvement de transfert (créé dans beginPrepa)
                $quantity = ($isSelectedByArticle || !isset($stockMovement))
                    ? $quantity
                    : $stockMovement->getQuantity();
            }
        }

        $movement = $this->stockMovementService->createMouvementStock($userNomade, $emplacementFrom, $quantity, $refArticle ?? $article, MouvementStock::TYPE_SORTIE, [
            'from' => $livraison,
        ]);

        $entityManager->persist($movement);

        return $movement;
    }

    public function deleteLigneRefOrNot(?PreparationOrderReferenceLine $ligne, Preparation $preparation, EntityManagerInterface $entityManager)
    {
        if ($ligne && empty($ligne->getQuantityToPick())) {
            $preparation->removeReferenceLine($ligne);
            $entityManager->remove($ligne);
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
                    if (!in_array($article->getStatut()->getCode(), [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE])) {
                        throw new Exception(self::ARTICLE_ALREADY_SELECTED);
                    } else {
                        $articleTransitStatus = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT);
                        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                        $referenceLine = $referenceLineRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                        $this->treatArticleSplitting(
                            $this->entityManager,
                            $article,
                            $mouvement['quantity'],
                            $referenceLine,
                            $articleTransitStatus
                        );
                    }
                } else {
                    $articleLine = $preparation->getArticleLine($article);
                    if ($articleLine) {
                        $articleLine->setPickedQuantity($mouvement['quantity']);
                    }
                }
            }
        }

        $this->entityManager->flush();
    }

    public function treatArticleSplitting(EntityManagerInterface        $entityManager,
                                          Article                       $article,
                                          int                           $quantity,
                                          PreparationOrderReferenceLine $referenceLine,
                                          Statut                        $statusArticle): void
    {
        if ($quantity && $quantity <= $article->getQuantite()) {
            $article->setStatut($statusArticle);
            $preparation = $referenceLine->getPreparation();
            $articleLine = $preparation->getArticleLine($article);

            if (!isset($articleLine)) {
                $articleLine = new PreparationOrderArticleLine();
                $articleLine
                    ->setArticle($article)
                    ->setPreparation($preparation)
                    ->setDeliveryRequestReferenceLine($referenceLine->getDeliveryRequestReferenceLine());
                $entityManager->persist($articleLine);
            }

            // si on a enlevé de la quantité à l'article : on enlève la difference à la quantité de la ligne article
            // si on a ajouté de la quantité à l'article : on enlève la ajoute à la quantité de la ligne article
            // si rien a changé on touche pas à la quantité de la ligne article
            $referenceLine->setQuantityToPick($referenceLine->getQuantityToPick() + ($articleLine->getPickedQuantity() - $quantity));
            $articleLine
                ->setQuantityToPick($quantity)
                ->setPickedQuantity($quantity);
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

    public function createMouvementsPrepaAndSplit(Preparation               $preparation,
                                                  Utilisateur               $user,
                                                  EntityManagerInterface    $entityManager): array {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $splitArticleLineIds = [];

        $articleLines = $preparation->getArticleLines();
        $articleTransitStatus = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
        $articleActiveStatus = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
        $now = new DateTime();

        foreach ($articleLines as $line) {
            $article = $line->getArticle();
            $mouvementAlreadySaved = $preparation->getArticleMovement($article);
            if (!$mouvementAlreadySaved) {
                $pickedQuantity = $line->getPickedQuantity();
                if ($article->getQuantite() >= $pickedQuantity) {
                    // scission des articles dont la quantité prélevée n'est pas totale
                    if ($article->getQuantite() > $pickedQuantity) {
                        if ($pickedQuantity > 0) {
                            $newArticle = [
                                'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                                'libelle' => $article->getLabel(),
                                'prix' => $article->getPrixUnitaire(),
                                'conform' => $article->getConform(),
                                'commentaire' => $article->getcommentaire(),
                                'quantite' => $pickedQuantity,
                                'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                                'statut' => Article::STATUT_EN_TRANSIT,
                                'refArticle' => $article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getId() : ''
                            ];

                            // copy of all free fields
                            $newArticle += $article->getFreeFields();

                            $insertedArticle = $this->articleDataService->newArticle($entityManager, $newArticle);
                            $newArticleLine = $line->clone()
                                ->setPreparation($line->getPreparation())
                                ->setQuantityToPick($line->getPickedQuantity())
                                ->setPickedQuantity($line->getPickedQuantity())
                                ->setArticle($insertedArticle);

                            $entityManager->persist($newArticleLine);

                            if ($line->getQuantityToPick() > $line->getPickedQuantity()) {
                                $line->setQuantityToPick($line->getQuantityToPick() - $line->getPickedQuantity());
                                $line->setPickedQuantity(0);
                                $splitArticleLineIds[] = $line->getId();
                            } else {
                                $preparation->removeArticleLine($line);
                                $line->setArticle(null); // To remove "A new entity was found through the relationship" error for Article entity
                                $entityManager->remove($line);
                                $article->setStatut($articleActiveStatus);
                            }
                            $article->setQuantite($article->getQuantite() - $pickedQuantity);

                            $location = $article->getEmplacement();
                            // Output movement of the picking quantity from a stock article
                            $outputMovement = $this->stockMovementService->createMouvementStock(
                                $user,
                                $location,
                                $pickedQuantity,
                                $article,
                                MouvementStock::TYPE_SORTIE,
                                [
                                    'date' => $now,
                                    'locationTo' => $location,
                                    'from' => $preparation,
                                ]
                            );
                            $entityManager->persist($outputMovement);

                            // article creation
                            $inputMovement = $this->stockMovementService->createMouvementStock(
                                $user,
                                $location,
                                $pickedQuantity,
                                $insertedArticle,
                                MouvementStock::TYPE_ENTREE,
                                [
                                    'date' => $now,
                                    'locationTo' => $location,
                                    'from' => $preparation,
                                ]
                            );
                            $entityManager->persist($inputMovement);

                            $transferMovement = $this->stockMovementService->createMouvementStock(
                                $user,
                                $location,
                                $pickedQuantity,
                                $insertedArticle,
                                MouvementStock::TYPE_TRANSFER,
                                [
                                    'date' => $now,
                                    'from' => $preparation,
                                ]
                            );
                            $entityManager->persist($transferMovement);
                        }
                        else {
                            $splitArticleLineIds[] = $line->getId();
                        }
                    }
                    else if ($pickedQuantity > 0) {
                        $article->setStatut($articleTransitStatus);
                        // création des mouvements de préparation pour les articles
                        $stockMovement = $this->stockMovementService->createMouvementStock(
                            $user,
                            $article->getEmplacement(),
                            $pickedQuantity,
                            $article,
                            MouvementStock::TYPE_TRANSFER,
                            [
                                'date' => $now,
                                'from' => $preparation,
                            ]
                        );
                        $entityManager->persist($stockMovement);
                    }
                    else {
                        throw new NegativeQuantityException($article);
                    }
                    $entityManager->flush();
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
                    $mouvement = $this->stockMovementService->createMouvementStock($user, $articleRef->getEmplacement(), $ligneArticle->getPickedQuantity(), $articleRef, MouvementStock::TYPE_TRANSFER, [
                        'date' => new DateTime(),
                        'from' => $preparation,
                    ]);

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
        return $splitArticleLineIds;
    }

    public function getDataForDatatable($params = null, $filterDemande = null): array {
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
            if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
                $this->refArticleDataService->updateRefArticleQuantities($entityManager, [$refArticle]);
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
            'Statut' => $preparation->getStatut() ? $this->formatService->status($preparation->getStatut()) : '',
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

        /** @var PreparationOrderReferenceLine $referenceLine */
        foreach ($preparation->getReferenceLines() as $referenceLine) {
            $referenceLine->setPickedQuantity(0);
        }

        /** @var PreparationOrderArticleLine $articleLine */
        foreach ($preparation->getArticleLines() as $articleLine) {
            $articleLine->setPickedQuantity(0);
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

    public function managePreRemovePreparation(Preparation $preparation, EntityManagerInterface $entityManager, MouvementStockService $mouvementStockService): array {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->security->getUser();

        $statutRepository = $entityManager->getRepository(Statut::class);
        $demande = $preparation->getDemande();
        $trackingMovements = $preparation->getTrackingMovements();
        foreach ($trackingMovements as $movement) {
            $preparation->removeTrackingMovement($movement);
        }

        $requestStatusDraft = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
        $statutActifArticle = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
        $statutConsommeArticle = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_INACTIF);

        if ($demande->getPreparations()->count() === 1) {
            $demande
                ->setStatut($requestStatusDraft);
        }

        /** @var PreparationOrderArticleLine $articleLine */
        foreach ($preparation->getArticleLines()->toArray() as $articleLine) {
            $article = $articleLine->getArticle();
            $article->setStatut($articleLine->isAutoGenerated() ? $statutConsommeArticle : $statutActifArticle);

            if ($articleLine->isAutoGenerated()){
                $stockMovement = $mouvementStockService->createMouvementStock(
                    $loggedUser,
                    null,
                    $articleLine->getQuantityToPick(),
                    $article,
                    MouvementStock::TYPE_SORTIE,
                    [
                        "date" => new DateTime('now'),
                        "locationTo" => $article->getEmplacement()
                    ]
                );

                $entityManager->persist($stockMovement);
            }

            $articleLine->setArticle(null);
            $articleLine->setPreparation(null);
            $entityManager->remove($articleLine);
        }

        $refToUpdate = [];

        /** @var PreparationOrderReferenceLine $referenceLine */
        foreach ($preparation->getReferenceLines() as $referenceLine) {
            $refArticle = $referenceLine->getReference();
            if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                $quantiteReservee = $refArticle->getQuantiteReservee();
                $quantityToPick = $referenceLine->getQuantityToPick();
                $newQuantiteReservee = ($quantiteReservee - $quantityToPick);
                $refArticle->setQuantiteReservee($newQuantiteReservee > 0 ? $newQuantiteReservee : 0);

                $newQuantiteReservee = $refArticle->getQuantiteReservee();
                $quantiteStock = $refArticle->getQuantiteStock();
                $newQuantiteDisponible = ($quantiteStock - $newQuantiteReservee);
                $refArticle->setQuantiteDisponible($newQuantiteDisponible > 0 ? $newQuantiteDisponible : 0);
            } else {
                $refToUpdate[] = $refArticle;
            }
            $entityManager->remove($referenceLine);
        }
        return $refToUpdate;
    }

    public function putPreparationLines($handle, Preparation $preparation): void {
        $preparationBaseData = $preparation->serialize();

        /** @var PreparationOrderReferenceLine $referenceLine */
        foreach ($preparation->getReferenceLines() as $referenceLine) {
            $referenceArticle = $referenceLine->getReference();

            $this->CSVExportService->putLine($handle, array_merge($preparationBaseData, [
                $referenceArticle->getReference() ?? '',
                $referenceArticle->getLibelle() ?? '',
                $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
                $referenceLine->getQuantityToPick() ?? 0,
                $referenceArticle->getBarCode()
            ]));
        }

        /** @var PreparationOrderArticleLine $articleLine */
        foreach ($preparation->getArticleLines() as $articleLine) {
            $article = $articleLine->getArticle();
            $articleFournisseur = $article->getArticleFournisseur();
            $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
            $reference = $referenceArticle ? $referenceArticle->getReference() : '';

            $this->CSVExportService->putLine($handle, array_merge($preparationBaseData, [
                $reference,
                $article->getLabel() ?? '',
                $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                $articleLine->getQuantityToPick(),
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

    public function getArticlesPrepaArrays(EntityManagerInterface $entityManager,
                                           array $preparations,
                                           bool $isIdArray = false): array {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $preparationsIds = !$isIdArray
            ? array_map(
                function ($preparationArray) {
                    return $preparationArray['id'];
                },
                $preparations
            )
            : $preparations;
        return array_merge(
            $articleRepository->getByPreparationsIds($preparationsIds),
            $referenceArticleRepository->getByPreparationsIds($preparationsIds)
        );
    }
}
