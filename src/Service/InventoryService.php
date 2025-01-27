<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryMission;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Repository\ArticleRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class InventoryService {

    public function __construct(
        private SettingsService         $settingService,
        private EntityManagerInterface  $entityManager,
        private TrackingMovementService $trackingMovementService,
        private MouvementStockService   $stockMovementService) {
    }

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

            $type = $diff < 0 ? MouvementStock::TYPE_INVENTAIRE_SORTIE : MouvementStock::TYPE_INVENTAIRE_ENTREE;
            $emplacement = $refOrArt->getEmplacement();
            $now = new DateTime();
            $mvt = $this->stockMovementService->createMouvementStock($user, $emplacement, abs($diff), $refOrArt, $type, [
                'date' => $now,
                'locationTo' => $emplacement,
                'comment' => $comment,
            ]);

            if ($isRef) {
                $refOrArt->setQuantiteStock($newQuantity);
            } else {
                $refOrArt->setQuantite($newQuantity);
                if ($newQuantity === 0) {
                    $refOrArt
                        ->setStatut($consumedStatus);

                    $articleLeave = $this->stockMovementService->createMouvementStock($user, $emplacement, abs($diff), $refOrArt, MouvementStock::TYPE_SORTIE, [
                        'date' => $now,
                        'locationTo' => $emplacement,
                        'comment' => $comment,
                    ]);

                    $this->entityManager->persist($articleLeave);
                    $this->entityManager->flush();

                    if ($refOrArt->getCurrentLogisticUnit()) {
                        $refOrArt
                            ->setCurrentLogisticUnit(null);

                        $movement = $this->trackingMovementService->persistTrackingMovement(
                            $this->entityManager,
                            $refOrArt->getTrackingPack() ?: $refOrArt->getBarCode(),
                            $emplacement,
                            $user,
                            $now,
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

    public function summarizeLocationInventory(EntityManagerInterface $entityManager,
                                               InventoryMission       $mission,
                                               Zone                   $zone,
                                               array                  $rfidTags): array {
        $articleRepository = $entityManager->getRepository(Article::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $storageRuleRepository = $entityManager->getRepository(StorageRule::class);

        $tagRFIDPrefix = $this->settingService->getValue($this->entityManager,Setting::RFID_PREFIX) ?: '';

        $rfidTags = Stream::from($rfidTags)
            ->filter(fn(string $tag) => str_starts_with($tag, $tagRFIDPrefix))
            ->unique()
            ->toArray();

        $locations = $locationRepository->findByMissionAndZone([$zone], $mission);

        $scannedArticlesByStorageRule = $articleRepository->findAvailableArticlesToInventory($rfidTags, $locations, [
            "groupByStorageRule" => true,
            "mode" => ArticleRepository::INVENTORY_MODE_SUMMARY,
        ]);

        $storageRules = $storageRuleRepository->findBy(['location' => $locations]);

        $minStr = $this->settingService->getValue($this->entityManager,Setting::RFID_KPI_MIN);
        $maxStr = $this->settingService->getValue($this->entityManager,Setting::RFID_KPI_MAX);
        $min = $minStr ? intval($minStr) : null;
        $max = $maxStr ? intval($maxStr) : null;

        $zoneInventoryIndicator = $zone->getInventoryIndicator() ?: 1;

        $locationsData = [];
        $inventoryData = [];
        $result = [];
        $numScannedObjects = 0;

        foreach ($storageRules as $storageRule) {
            $rowMissingReferenceResult = null;

            $storageRuleId = $storageRule->getId();
            $location = $storageRule->getLocation();
            $locationId = $location?->getId();
            $locationLabel = $location?->getLabel();
            $referenceArticle = $storageRule->getReferenceArticle();
            $reference = $referenceArticle?->getReference();

            $scannedArticles = $scannedArticlesByStorageRule[$storageRuleId] ?? [];

            $articleCounter = count($scannedArticles); // available and unavailable article counter
            $numScannedObjects += $articleCounter;

            $key = "location" . $locationId;
            $rowLocationResult = $locationsData[$key] ?? [
                "location" => $locationLabel,
                "locationId" => $locationId,
                "articleCounter" => 0,
                "storageRuleCounter" => 0,
            ];

            $rowLocationResult["articleCounter"] += $articleCounter;
            $rowLocationResult["storageRuleCounter"] += 1;

            $rowLocationResult["articles"] = array_merge(
                $rowLocationResult["articles"] ?? [],
                $scannedArticles
            );

            $locationsData[$key] = $rowLocationResult;

            if ($articleCounter === 0) {
                $rowMissingReferenceResult = [
                    "location" => $locationLabel,
                    "reference" => $reference,
                    "missing" => true,
                ];
                $result[] = $rowMissingReferenceResult;
            }
        }

        // calculate percentage and save in inventory stats and scanned articles
        foreach($locationsData as $locationRow) {
            $ratio = $zoneInventoryIndicator
                ? floor(($locationRow['articleCounter'] / ($locationRow['storageRuleCounter'] * $zoneInventoryIndicator)) * 100)
                : 0;

            $inventoryData[$locationRow["locationId"]] = [
                "locationId" => $locationRow["locationId"],
                "ratio" => $ratio,
                "articles" => $locationRow["articles"] ?? [],
            ];

            $result[] = [
                "location" => $locationRow['location'],
                "ratio" => $ratio,
            ];
        }

        $locationsData = Stream::from($result)
            ->filter(fn(array $line) => (
                !isset($line["ratio"])
                || (
                    (!isset($min) || $line["ratio"] >= $min)
                    && (!isset($max) || $line["ratio"] <= $max)
                )
            ))
            ->values();

        return [
            "numScannedObjects" => $numScannedObjects,
            "lines" => $locationsData,
            "inventoryData" => $inventoryData
        ];
    }


    public function clearInventoryZone(InventoryMission $mission): void {
        $inventoryLocationMissionArray = $mission->getInventoryLocationMissions();
        foreach ($inventoryLocationMissionArray as $inventoryLocationMission) {
            $inventoryLocationMission
                ->setOperator(null)
                ->setScannedAt(null)
                ->setPercentage(null)
                ->setArticles([]);
        }
    }

}
