<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryLocationMissionReferenceArticle;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\AdMob\Date;
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

    public function parseAndSummarizeInventory(array $data, EntityManagerInterface $entityManager, Utilisateur $user) {
        $articleRepository = $entityManager->getRepository(Article::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $zone = $entityManager->getRepository(Zone::class)->find($data["zone"]);
        $mission = $entityManager->getRepository(InventoryMission::class)->find($data["mission"]);
        $scannedArticles = Stream::from($entityManager->getRepository(Article::class)->findBy(['RFIDtag' => json_decode($data['rfidTags'])]))
            ->map(fn (Article $article) => $article->getId())
            ->toArray();

        $locations = $locationRepository->findByMissionAndZone([$zone], $mission);
        $locationIDS = Stream::from($locations)
            ->map(fn (Emplacement $location) => $location->getId())
            ->toArray();

        $expected = $articleRepository->findGroupedByReferenceArticleAndLocation($locationIDS);
        $actual = $articleRepository->findGroupedByReferenceArticleAndLocation($locationIDS, $scannedArticles);
        $references = Stream::from($expected)
            ->keymap(fn(array $expectedState) => [$expectedState['referenceEntity'], $entityManager->find(ReferenceArticle::class, $expectedState['referenceEntity'])])
            ->toArray();
        $formattedActual = Stream::from($actual)
            ->keymap(fn(array $actualQuantity) => [$actualQuantity['location'] . '---' . $actualQuantity['reference'], $actualQuantity['quantity']])
            ->toArray();

        $result = [];
        foreach($mission->getInventoryLocationMissions() as $locationMission) {
            foreach ($locationMission->getInventoryLocationMissionReferenceArticles() as $line) {
                $entityManager->remove($line);
            }
        }
        $entityManager->flush();

        $min = intval($settingRepository->getOneParamByLabel(Setting::RFID_KPI_MIN));
        $max = intval($settingRepository->getOneParamByLabel(Setting::RFID_KPI_MAX));
        foreach ($expected as $expectedResult) {
            $quantity = $expectedResult['quantity'];
            $reference = $expectedResult['reference'];
            $location = $expectedResult['location'];
            $linkedLine = $mission
                ->getInventoryLocationMissions()
                ->filter(fn(InventoryLocationMission $inventoryLocationMission) => $inventoryLocationMission->getLocation()->getLabel() === $location)
                ->first();

            $actualQuantity = $formattedActual[$location . '---' . $reference] ?? -1;
            $percentage = $actualQuantity === -1 ? 0 : floor((intval($actualQuantity) / intval($quantity)) * 100);
            $line = new InventoryLocationMissionReferenceArticle();
            $line
                ->setInventoryLocationMission($linkedLine)
                ->setOperator($user)
                ->setPercentage($percentage)
                ->setScannedAt(new DateTime("now"))
                ->setReferenceArticle($references[$expectedResult['referenceEntity']]);
            $entityManager->persist($line);
            $entityManager->flush();
            if ($percentage >= $min && $percentage <= $max) {
                $result[] = [
                    'reference' => $reference,
                    'location' => $location,
                    'ratio' => $percentage,
                ];
            }
        }

        return $result;
    }

}
