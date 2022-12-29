<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class InventoryService {

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    public function doTreatAnomaly(int         $idEntry,
                                   string      $barCode,
                                   bool        $isRef,
                                   int         $newQuantity,
                                   ?string     $comment,
                                   Utilisateur $user): array {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);

        $quantitiesAreEqual = true;
        $consumedStatus = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);
        if ($isRef) {
            $refOrArt = $referenceArticleRepository->findOneBy(['barCode' => $barCode])
                ?: $referenceArticleRepository->findOneBy(['reference' => $barCode]);
            $quantity = $refOrArt->getQuantiteStock();
        }
        else {
            /** @var Article $refOrArt */
            $refOrArt = $articleRepository->findOneBy(['barCode' => $barCode]) ?: $articleRepository->findOneByReference($barCode);
            $quantity = $refOrArt->getQuantite();
        }

        $diff = ($newQuantity - $quantity);

        if ($diff != 0) {
            $statusRequired = $isRef
                ? [ReferenceArticle::STATUT_ACTIF]
                : [
                    Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE,
                ];
            if (!in_array($refOrArt->getStatut()->getCode(), $statusRequired)) {
                throw new ArticleNotAvailableException();
            }

            $reference = $refOrArt instanceof ReferenceArticle
                ? $refOrArt
                : $refOrArt->getReferenceArticle();

            if ($reference && $reference->isInRequestsInProgress()) {
                throw new RequestNeedToBeProcessedException();
            }

            $mvt = new MouvementStock();
            $mvt
                ->setUser($user)
                ->setDate(new DateTime('now'))
                ->setComment(StringHelper::cleanedComment($comment))
                ->setQuantity(abs($diff));

            $emplacement = $refOrArt->getEmplacement();
            $mvt->setEmplacementFrom($emplacement);
            $mvt->setEmplacementTo($emplacement);
            if ($isRef) {
                $mvt->setRefArticle($refOrArt);
                //TODO à supprimer quand la quantité sera calculée directement via les mouvements de stock
                $refOrArt->setQuantiteStock($newQuantity);
            }
            else {
                $mvt->setArticle($refOrArt);
                //TODO à supprimer quand la quantité sera calculée directement via les mouvements de stock
                $refOrArt->setQuantite($newQuantity);
                if ($newQuantity === 0) {
                    $refOrArt
                        ->setStatut($consumedStatus);

                    $articleLeave = new MouvementStock();
                    $articleLeave
                        ->setUser($user)
                        ->setArticle($refOrArt)
                        ->setEmplacementFrom($emplacement)
                        ->setEmplacementTo($emplacement)
                        ->setDate(new DateTime('now'))
                        ->setComment(StringHelper::cleanedComment($comment))
                        ->setType(MouvementStock::TYPE_SORTIE)
                        ->setQuantity(abs($diff));

                    $this->entityManager->persist($articleLeave);
                    $this->entityManager->flush();

                    if ($refOrArt->getCurrentLogisticUnit()) {
                        $refOrArt
                            ->setCurrentLogisticUnit(null);

                        $movement = $this->trackingMovementService->persistTrackingMovement(
                            $this->entityManager,
                            $refOrArt->getBarCode(),
                            $emplacement,
                            $user,
                            new DateTime('now'),
                            true,
                            TrackingMovement::TYPE_PICK_LU,
                            false,
                            [
                                'entityManager' => $this->entityManager,
                                'mouvementStock' => $articleLeave
                            ]
                        );

                        if ($movement['movement']) {
                            $this->entityManager->persist($movement['movement']);
                            $this->entityManager->flush();
                        }

                    }
                }
            }

            $typeMvt = $diff < 0 ? MouvementStock::TYPE_INVENTAIRE_SORTIE : MouvementStock::TYPE_INVENTAIRE_ENTREE;
            $mvt->setType($typeMvt);

            $this->entityManager->persist($mvt);
            $quantitiesAreEqual = false;
        }

        $entry = $inventoryEntryRepository->find($idEntry);
        $allEntriesToTreat = $inventoryEntryRepository->findBy([
            'refArticle' => $entry->getRefArticle(),
            'article' => $entry->getArticle(),
            'anomaly' => true,
        ]);

        $treatedEntries = [];
        foreach ($allEntriesToTreat as $entryToTreat) {
            $treatedEntries[] = $entryToTreat->getId();
            $entryToTreat
                ->setQuantity($newQuantity)
                ->setAnomaly(false);
        }

        $refOrArt->setDateLastInventory(new DateTime('now'));

        $this->entityManager->flush();

        return [
            'treatedEntries' => $treatedEntries,
            'quantitiesAreEqual' => $quantitiesAreEqual,
        ];
    }

    public function isInMissionInSamePeriod(ReferenceArticle|Article $refOrArticle,
                                            InventoryMission         $mission,
                                            bool                     $isRef): bool {
        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);
        $beginDate = clone ($mission->getStartPrevDate())->setTime(0, 0, 0);
        $endDate = clone ($mission->getEndPrevDate())->setTime(23, 59, 59);
        if ($isRef) {
            $nbMissions = $inventoryMissionRepository->countByRefAndDates($refOrArticle, $beginDate, $endDate);
        }
        else {
            $nbMissions = $inventoryMissionRepository->countByArtAndDates($refOrArticle, $beginDate, $endDate);
        }

        return $nbMissions > 0;
    }

    public function generateMissions() {
        $rules = $this->entityManager->getRepository(InventoryMissionRule::class)->findAll();

        $now = new DateTime();
        $now->setTime(0, 0);

        $firstSundayThisMonth = new DateTime("first sunday of {$now->format("Y")}-{$now->format("m")}");
        $firstSundayThisMonth->setTime(0, 0);

        $isFirstSunday = $firstSundayThisMonth == $now;
        foreach ($rules as $rule) {
            $nextRun = null;
            if($rule->getLastRun()) {
                $nextRun = clone $rule->getLastRun();
                $nextRun->modify("+{$rule->getPeriodicity()} {$rule->getPeriodicityUnit()}");
                $nextRun->setTime(0, 0);
            }

            if($rule->getPeriodicityUnit() === InventoryMissionRule::MONTHS && !$isFirstSunday
                || $nextRun && $nextRun > $now) {
                continue;
            }

            $rule->setLastRun($now);

            $this->createMission($rule);
        }
    }

    public function createMission(InventoryMissionRule $rule) {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);

        $mission = new InventoryMission();
        $mission->setName($rule->getLabel());
        $mission->setStartPrevDate(new DateTime("tomorrow"));
        $mission->setEndPrevDate(new DateTime("tomorrow +{$rule->getDuration()} {$rule->getDurationUnit()}"));
        $mission->setCreator($rule);

        $frequencies = Stream::from($rule->getCategories())
            ->map(fn(InventoryCategory $category) => $category->getFrequency())
            ->toArray();

        $this->entityManager->persist($mission);

        foreach ($frequencies as $frequency) {
            // récupération des réf et articles à inventorier (fonction date dernier inventaire)
            $referencesToInventory = $referenceArticleRepository->iterateReferencesToInventory($frequency,
                $mission);
            $articlesToInventory = $articleRepository->iterateArticlesToInventory($frequency, $mission);

            $treated = 0;

            foreach ($referencesToInventory as $reference) {
                $reference->addInventoryMission($mission);
                $treated++;
                if ($treated >= 500) {
                    $treated = 0;
                    $this->entityManager->flush();
                }
            }

            $treated = 0;
            $this->entityManager->flush();

            /** @var Article $article */
            foreach ($articlesToInventory as $article) {
                $article->addInventoryMission($mission);
                $treated++;
                if ($treated >= 500) {
                    $treated = 0;
                    $this->entityManager->flush();
                }
            }

            $this->entityManager->flush();

            // lissage des réf et articles jamais inventoriés
            $nbRefAndArtToInv = $referenceArticleRepository->countActiveByFrequencyWithoutDateInventory($frequency);
            $nbToInv = $nbRefAndArtToInv['nbRa'] + $nbRefAndArtToInv['nbA'];

            $limit = (int) ($nbToInv / ($frequency->getNbMonths() * 4));

            $listRefNextMission = $referenceArticleRepository->findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency,
                $limit / 2);
            $listArtNextMission = $articleRepository->findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency,
                $limit / 2);

            /** @var ReferenceArticle $ref */
            foreach ($listRefNextMission as $ref) {
                $alreadyInMission = $this->isInMissionInSamePeriod($ref, $mission, true);
                if (!$alreadyInMission) {
                    $ref->addInventoryMission($mission);
                }
            }
            /** @var Article $art */
            foreach ($listArtNextMission as $art) {
                $alreadyInMission = $this->isInMissionInSamePeriod($art, $mission, false);
                if (!$alreadyInMission) {
                    $art->addInventoryMission($mission);
                }
            }

            $this->entityManager->flush();
        }
    }

}
