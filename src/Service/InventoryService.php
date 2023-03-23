<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryLocationMissionReferenceArticle;
use App\Entity\Inventory\InventoryMission;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Repository\ArticleRepository;
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

    public function parseAndSummarizeInventory(EntityManagerInterface $entityManager,
                                               InventoryMission       $mission,
                                               Zone                   $zone,
                                               array                  $rfidTags,
                                               Utilisateur            $user): array {
        $articleRepository = $entityManager->getRepository(Article::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $storageRuleRepository = $entityManager->getRepository(StorageRule::class);

        $tagRFIDPrefix = $settingRepository->getOneParamByLabel(Setting::RFID_PREFIX) ?: '';

        $rfidTags = Stream::from($rfidTags)
            ->filter(fn(string $tag) => str_starts_with($tag, $tagRFIDPrefix))
            ->toArray();

        $locations = $locationRepository->findByMissionAndZone([$zone], $mission);

        $scannedArticlesByStorageRule = $articleRepository->findAvailableArticlesToInventory($rfidTags, $locations, [
            "groupByStorageRule" => true,
            "mode" => ArticleRepository::INVENTORY_MODE_SUMMARY
        ]);

        $storageRules = $storageRuleRepository->findBy(['location' => $locations]);

        $now = new DateTime('now');

        $minStr = $settingRepository->getOneParamByLabel(Setting::RFID_KPI_MIN);
        $maxStr = $settingRepository->getOneParamByLabel(Setting::RFID_KPI_MAX);
        $min = $minStr ? intval($minStr) : null;
        $max = $maxStr ? intval($maxStr) : null;

        $this->clearInventoryZone($entityManager, $mission, $zone);

        $zoneInventoryIndicator = $zone->getInventoryIndicator() ?: 1;

        $lines = [];
        $numScannedObjects = 0;

        foreach ($storageRules as $storageRule) {
            $key = null;
            $storageRuleId = $storageRule->getId();
            $location = $storageRule->getLocation();
            $locationId = $location?->getId();
            $locationLabel = $location?->getLabel();
            $referenceArticle = $storageRule->getReferenceArticle();
            $reference = $referenceArticle?->getReference();

            $scannedArticles = $scannedArticlesByStorageRule[$storageRuleId] ?? [];
            $articleCounter = count($scannedArticles);
            $numScannedObjects += $articleCounter;

            if ($articleCounter > 0) {
                $key = "location" . $locationId;
                $rowResult = $lines[$key] ?? [
                    "location" => $locationLabel,
                    "articleCounter" => 0,
                    "storageRuleCounter" => 0,
                ];

                $rowResult["articleCounter"] += $articleCounter;
                $rowResult["storageRuleCounter"] += 1;
            }
            else {
                $rowResult = [
                    "location" => $locationLabel,
                    "reference" => $reference,
                    "missing" => true,
                ];
            }

            /*$linkedLine = $rowResult["linkedLine"] ?? null;
            if (!isset($linkedLine)) {
                $linkedLine = $mission
                    ->getInventoryLocationMissions()
                    ->filter(fn(InventoryLocationMission $inventoryLocationMission) => $inventoryLocationMission->getLocation()?->getId() === $locationId)
                    ->first() ?: null;
                $rowResult["linkedLine"] = $linkedLine;
            }

            if ($linkedLine) {
                // TODO WIIS-9373 remove ??
                $percentage = empty($expectedQuantity) ? 0 : floor($actualQuantity / $expectedQuantity) * 100;
                $inventoryLine = new InventoryLocationMissionReferenceArticle();
                $inventoryLine
                    ->setInventoryLocationMission($linkedLine)
                    ->setOperator($user)
                    ->setPercentage($percentage)
                    ->setScannedAt($now)
                    ->setReferenceArticle($referenceArticle);
                $entityManager->persist($inventoryLine);
            }
            */

            if (isset($key)) {
                $lines[$key] = $rowResult;
            }
            else {
                $lines[] = $rowResult;
            }
        }

        $lines = Stream::from($lines)
            ->map(fn(array $line) => (
                ($line['missing'] ?? false)
                    ? [
                        "location" => $line["location"],
                        "reference" => $line["reference"],
                        "missing" => true,
                    ]
                    : [
                        "location" => $line['location'],
                        "ratio" => $zoneInventoryIndicator
                            ? floor(($line['articleCounter'] / ($line['storageRuleCounter'] * $zoneInventoryIndicator)) * 100)
                            : 0
                    ]
            ))
            ->filter(fn(array $line) => (
                !isset($line["ratio"])
                || (
                    (!isset($min) || $line["ratio"] >= $min)
                    && (!isset($max) || $line["ratio"] <= $max)
                )
            ))
            ->values();

        $entityManager->flush();

        return [
            "numScannedObjects" => $numScannedObjects,
            "lines" => $lines
        ];
    }


    public function clearInventoryZone(EntityManagerInterface $entityManager,
                                       InventoryMission       $mission,
                                       Zone                   $zone): void {
        $inventoryLocationMissionArray = $mission->getInventoryLocationMissions();
        foreach ($inventoryLocationMissionArray as $inventoryLocationMission) {
            if ($inventoryLocationMission->getLocation()?->getZone()?->getId() === $zone->getId()) {
                foreach ($inventoryLocationMission->getInventoryLocationMissionReferenceArticles() as $line) {
                    $inventoryLocationMission->removeInventoryLocationMissionReferenceArticle($line);
                    $entityManager->remove($line);
                }
                $inventoryLocationMission->setInventoryLocationMissionReferenceArticles([]);
            }
        }
    }

}
