<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorWrapper;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
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

    public function treatPreparation(Preparation            $preparation,
                                                            $userNomade,
                                     Emplacement            $emplacement,
                                     array                  $articleLinesToKeep,
                                     EntityManagerInterface $entityManager = null): ?Preparation
    {
        if (!isset($entityManager)) {
            $entityManager = $this->entityManager;
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $demande = $preparation->getDemande();
        /** @var PreparationOrderArticleLine $articleLine */
        foreach ($preparation->getArticleLines() as $articleLine) {
            $article = $articleLine->getArticle();
            if ($articleLine->getPickedQuantity() > 0) {
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
            return $this->persistPreparationFromOldOne($preparation, $demande, $statutRepository, $articleRepository, $articleLinesToKeep, $entityManager);
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
        foreach ($listOfArticleSplitted as $lineId) {
            /** @var PreparationOrderArticleLine $line */
            $line = $articleRepository->find($lineId);
            $newPreparation->addArticleLine($line);
        }

        foreach ($preparation->getReferenceLines() as $ligneArticlePreparation) {
            $refArticle = $ligneArticlePreparation->getReference();
            $pickedQuantity = $ligneArticlePreparation->getPickedQuantity();
            if ($ligneArticlePreparation->getQuantityToPick() !== $pickedQuantity) {
                $newLigneArticle = new PreparationOrderReferenceLine();
                $selectedQuantityForPreviousLigne = $ligneArticlePreparation->getPickedQuantity() ?? 0;
                $newQuantity = ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE)
                    ? ($ligneArticlePreparation->getQuantityToPick() - $selectedQuantityForPreviousLigne)
                    : $ligneArticlePreparation->getQuantityToPick();
                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $ligneArticlePreparation->setQuantityToPick($ligneArticlePreparation->getPickedQuantity() ?? 0);
                }
                $newLigneArticle
                    ->setPreparation($newPreparation)
                    ->setReference($refArticle)
                    ->setQuantityToPick($newQuantity);

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
                $article->setInactiveSince(new DateTime());

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
        if ($ligne && $ligne->getQuantityToPick() === 0) {
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
                $articleLine = $this->createArticleLine($article, $preparation);
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

    public function createMouvementsPrepaAndSplit(Preparation $preparation,
                                                  Utilisateur $user,
                                                  EntityManagerInterface $entityManager): array
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $splitArticleLineIds = [];

        $articleLines = $preparation->getArticleLines();
        $articleTransitStatus = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
        $articleActiveStatus = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);

        foreach ($articleLines as $line) {
            $article = $line->getArticle();
            $mouvementAlreadySaved = $preparation->getArticleMovement($article);
            if (!$mouvementAlreadySaved) {
                $pickedQuantity = $line->getPickedQuantity();
                $selected = !(empty($pickedQuantity));
                $article->setStatut($selected ? $articleTransitStatus : $articleActiveStatus);

                if ($article->getQuantite() >= $pickedQuantity) {
                    // scission des articles dont la quantité prélevée n'est pas totale
                    if ($article->getQuantite() > $pickedQuantity) {
                        $newArticle = [
                            'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                            'libelle' => $article->getLabel(),
                            'prix' => $article->getPrixUnitaire(),
                            'conform' => !$article->getConform(),
                            'commentaire' => $article->getcommentaire(),
                            'quantite' => $selected ? ($article->getQuantite() - $line->getPickedQuantity()) : 0,
                            'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                            'statut' => $selected ? Article::STATUT_ACTIF : Article::STATUT_INACTIF,
                            'refArticle' => $article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getId() : ''
                        ];

                        // copy of all free fields
                        $newArticle += $article->getFreeFields();

                        $insertedArticle = $this->articleDataService->newArticle($newArticle, $entityManager);
                        if ($selected) {
                            if ($line->getQuantityToPick() > $line->getPickedQuantity()) {
                                $newArticleLine = $this->createArticleLine($insertedArticle, $preparation);
                                $newArticleLine->setQuantityToPick($line->getQuantityToPick() - $pickedQuantity);
                                $entityManager->persist($newArticleLine);
                                $splitArticleLineIds[] = $newArticleLine->getId();
                            }
                            $article->setQuantite($pickedQuantity);
                        } else {
                            $preparation->removeArticleLine($line);
                            $splitArticleLineIds[] = $line->getId();
                        }
                        $entityManager->flush();
                    }
                    if ($selected) {
                        // création des mouvements de préparation pour les articles
                        $mouvement = new MouvementStock();
                        $mouvement
                            ->setUser($user)
                            ->setArticle($article)
                            ->setQuantity($pickedQuantity)
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
        return $splitArticleLineIds;
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

    public function managePreRemovePreparation(Preparation $preparation, EntityManagerInterface $entityManager): array {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $demande = $preparation->getDemande();

        $requestStatusDraft = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
        $statutActifArticle = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);

        if ($demande->getPreparations()->count() === 1) {
            $demande
                ->setStatut($requestStatusDraft);
        }

        /** @var PreparationOrderArticleLine $articleLine */
        foreach ($preparation->getArticleLines()->toArray() as $articleLine) {
            $article = $articleLine->getArticle();
            $article->setStatut($statutActifArticle);

            $articleLine->setArticle(null);
            $articleLine->setPreparation(null);
            $entityManager->remove($articleLine);
        }

        $refToUpdate = [];

        /** @var PreparationOrderReferenceLine $referenceLine */
        foreach ($preparation->getReferenceLines() as $referenceLine) {
            $refArticle = $referenceLine->getReference();
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
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

    public function createArticleLine(Article $article,
                                      Preparation $preparation,
                                      int $quantityToPick = 0,
                                      int $pickedQuantity = 0): PreparationOrderArticleLine {
        $articleLine = new PreparationOrderArticleLine();
        $articleLine
            ->setQuantityToPick($quantityToPick)
            ->setPickedQuantity($pickedQuantity)
            ->setArticle($article)
            ->setPreparation($preparation);
        return $articleLine;
    }
}
