<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\MouvementStock;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\TrackingMovement;
use App\Exceptions\FormException;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Contracts\Service\Attribute\Required;

class ReceptionControllerService
{

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public UserService $userService;

    public function validateQuantities(array $payload): void
    {
        foreach ($payload as $line) {
            if ($line["quantityToReceive"] < 0) {
                throw new FormException("La quantité reçue doit être positive");
            }
        }
    }

    public function processReceptionRow(Reception               $reception,
                                        Collection              $receptionReferenceArticles,
                                        array                   $row,
                                        Emplacement             $receptionLocation,
                                        DateTime                $now,
                                        MouvementStockService   $mouvementStockService,
                                        TrackingMovementService $trackingMovementService,
                                        ArticleDataService      $articleDataService): void
    {
        $quantityReceived = $row['receivedQuantity'];
        $receptionReferenceArticle = $receptionReferenceArticles
            ->filter(fn(ReceptionReferenceArticle $r) => $r->getId() === $row['id'])
            ->first();

        if (!$receptionReferenceArticle) {
            throw new FormException("La ligne de réception n'existe pas");
        }

        $this->checkQuantities($receptionReferenceArticle, $quantityReceived);

        if ($receptionReferenceArticle->getReferenceArticle()->getArticlesFournisseur()->isEmpty()) {
            throw new FormException("L'article fournisseur n'est pas renseigné");
        }

        $referenceArticle = $receptionReferenceArticle->getReferenceArticle();
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
            $this->processReferenceQuantityType(
                $reception,
                $receptionReferenceArticle,
                $receptionLocation,
                $quantityReceived,
                $now,
                $mouvementStockService,
                $trackingMovementService
            );
        } else {
            $this->processArticleQuantityType(
                $reception,
                $receptionReferenceArticle,
                $receptionLocation,
                $quantityReceived,
                $now,
                $mouvementStockService,
                $trackingMovementService,
                $articleDataService
            );
        }
    }

    public function checkQuantities(ReceptionReferenceArticle $receptionReferenceArticle, int $quantityReceived): void
    {
        $quantityToReceive = $receptionReferenceArticle->getQuantiteAR();
        if ($quantityToReceive < ($quantityReceived + $receptionReferenceArticle->getQuantite())) {
            throw new FormException("La quantité reçue ne peut pas être supérieure à la quantité à recevoir");
        }
    }

    public function processReferenceQuantityType(Reception                 $reception,
                                                 ReceptionReferenceArticle $receptionReferenceArticle,
                                                 Emplacement               $receptionLocation,
                                                 int                       $quantityReceived,
                                                 DateTime                  $now,
                                                 MouvementStockService     $mouvementStockService,
                                                 TrackingMovementService   $trackingMovementService): void
    {
        $referenceArticle = $receptionReferenceArticle->getReferenceArticle();
        $this->createTrackingAndStockMovementReception($mouvementStockService, $quantityReceived, $referenceArticle, $reception, $receptionLocation, $now, $trackingMovementService, $receptionReferenceArticle);
    }

    public function processArticleQuantityType(Reception                 $reception,
                                               ReceptionReferenceArticle $receptionReferenceArticle,
                                               Emplacement               $receptionLocation,
                                               int                       $quantityReceived,
                                               DateTime                  $now,
                                               MouvementStockService     $mouvementStockService,
                                               TrackingMovementService   $trackingMovementService,
                                               ArticleDataService        $articleDataService): void
    {
        $articleArray = [
            "receptionReferenceArticle" => $receptionReferenceArticle,
            "refArticle" => $receptionReferenceArticle->getReferenceArticle(),
            "conform" => !$receptionReferenceArticle->getAnomalie(),
            "articleFournisseur" => $receptionReferenceArticle->getReferenceArticle()->getArticlesFournisseur()->first()->getId(),
            "quantite" => $quantityReceived,
            "emplacement" => $receptionLocation,
        ];

        if ($receptionReferenceArticle->getUnitPrice() !== null) {
            $articleArray["prix"] = $receptionReferenceArticle->getUnitPrice();
        }

        $article = $articleDataService->newArticle($this->entityManager, $articleArray);

        $this->createTrackingAndStockMovementReception($mouvementStockService, $quantityReceived, $article, $reception, $receptionLocation, $now, $trackingMovementService, $receptionReferenceArticle);
    }

    public function updateReceptionStatus(Reception $reception, $receptionReferenceArticles, EntityRepository $statusRepository): void
    {
        $isReceptionPartial = $receptionReferenceArticles
                ->filter(fn(ReceptionReferenceArticle $r) => $r->getQuantiteAR() !== $r->getQuantite())
                ->count() > 0;

        if ($isReceptionPartial) {
            $statusReceptionPartial = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_PARTIELLE);
            $reception->setStatut($statusReceptionPartial);
        } else {
            $statusReceptionTotal = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_TOTALE);
            $reception->setStatut($statusReceptionTotal);
        }
    }

    public function createTrackingAndStockMovementReception(MouvementStockService     $mouvementStockService,
                                                            int                       $quantityReceived,
                                                            Article|ReferenceArticle  $article,
                                                            Reception                 $reception,
                                                            Emplacement               $receptionLocation,
                                                            DateTime                  $now,
                                                            TrackingMovementService   $trackingMovementService,
                                                            ReceptionReferenceArticle $receptionReferenceArticle): void
    {
        $stockMovement = $mouvementStockService->createMouvementStock(
            $this->userService->getUser(),
            null,
            $quantityReceived,
            $article,
            MouvementStock::TYPE_ENTREE,
            [
                "from" => $reception,
                "locationTo" => $receptionLocation,
                "date" => $now,
            ]
        );

        $createdMvt = $trackingMovementService->createTrackingMovement(
            $article->getBarCode(),
            $receptionLocation,
            $this->userService->getUser(),
            $now,
            true,
            true,
            TrackingMovement::TYPE_DEPOSE,
            [
                'mouvementStock' => $stockMovement,
                'quantity' => $stockMovement->getQuantity(),
                'from' => $reception,
                'receptionReferenceArticle' => $receptionReferenceArticle,
            ]
        );

        $receptionReferenceArticle->setQuantite($receptionReferenceArticle->getQuantite() + $quantityReceived);

        $this->entityManager->persist($stockMovement);
        $this->entityManager->persist($createdMvt);
    }

}
