<?php

namespace App\Service\mobile;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\MouvementStock;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Tracking\TrackingMovement;
use App\Exceptions\FormException;
use App\Service\ArticleDataService;
use App\Service\MouvementStockService;
use App\Service\Tracking\TrackingMovementService;
use App\Service\UserService;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class MobileReceptionService
{

    #[Required]
    public UserService $userService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public MouvementStockService $mouvementStockService;

    #[Required]
    public ArticleDataService $articleDataService;

    private function validateQuantities(mixed $receivedQuantity): void
    {
        if (!is_int($receivedQuantity)) {
            throw new FormException("La quantité reçue doit être sous forme entière");
        }

        if ($receivedQuantity <= 0) {
            throw new FormException("La quantité reçue doit être supérieure ou égale à 1");
        }
    }

    public function processReceptionRow(EntityManagerInterface $entityManager,
                                        Reception              $reception,
                                        Collection             $receptionReferenceArticles,
                                        array                  $row,
                                        DateTime               $now): void
    {
        $quantityReceived = $row['receivedQuantity'];
        $receptionReferenceArticle = $receptionReferenceArticles
            ->filter(static fn(ReceptionReferenceArticle $receptionReferenceArticle) => $receptionReferenceArticle->getId() === $row['id'])
            ->first();

        if (!$receptionReferenceArticle) {
            throw new FormException("La ligne de réception n'existe pas");
        }

        $this->validateQuantities($quantityReceived);
        $this->checkQuantities($receptionReferenceArticle, $quantityReceived);

        $referenceArticle = $receptionReferenceArticle->getReferenceArticle();
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
            $this->processReferenceQuantityType(
                $entityManager,
                $reception,
                $receptionReferenceArticle,
                $quantityReceived,
                $now,
            );
        }
        else {
            $this->processArticleQuantityType(
                $entityManager,
                $reception,
                $receptionReferenceArticle,
                $quantityReceived,
                $now,
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

    public function processReferenceQuantityType(EntityManagerInterface    $entityManager,
                                                 Reception                 $reception,
                                                 ReceptionReferenceArticle $receptionReferenceArticle,
                                                 int                       $quantityReceived,
                                                 DateTime                  $now): void
    {
        $referenceArticle = $receptionReferenceArticle->getReferenceArticle();
        $this->createTrackingAndStockMovementReception($entityManager, $quantityReceived, $referenceArticle, $reception, $now, $receptionReferenceArticle);
    }

    public function processArticleQuantityType(EntityManagerInterface    $entityManager,
                                               Reception                 $reception,
                                               ReceptionReferenceArticle $receptionReferenceArticle,
                                               int                       $quantityReceived,
                                               DateTime                  $now): void
    {
        if ($receptionReferenceArticle->getReferenceArticle()->getArticlesFournisseur()->isEmpty()) {
            throw new FormException("La référence {$receptionReferenceArticle->getReferenceArticle()->getReference()} ne peut pas être réceptionnée car elle n'est liée à aucun article fournisseur");
        }

        /** @var ArticleFournisseur $supplierArticle */
        $supplierArticle = $receptionReferenceArticle->getReferenceArticle()->getArticlesFournisseur()->first() ?: null;

        $articleArray = [
            "refArticle" => $receptionReferenceArticle->getReferenceArticle(),
            "conform" => !$receptionReferenceArticle->getAnomalie(),
            "articleFournisseur" => $supplierArticle?->getId(),
            "quantite" => $quantityReceived,
            "emplacement" => $reception->getLocation(),
        ];

        if ($receptionReferenceArticle->getUnitPrice() !== null) {
            $articleArray["prix"] = $receptionReferenceArticle->getUnitPrice();
        }

        $article = $this->articleDataService->newArticle($entityManager, $articleArray);

        $receptionReferenceArticle->addArticle($article);

        $this->createTrackingAndStockMovementReception($entityManager, $quantityReceived, $article, $reception, $now, $receptionReferenceArticle);
    }

    public function updateReceptionStatus(EntityManagerInterface $entityManager, Reception $reception): void
    {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $receptionReferenceArticles = $reception->getReceptionReferenceArticles();

        $isReceptionPartial = Stream::from($receptionReferenceArticles)
                ->filter(fn(ReceptionReferenceArticle $receptionReferenceArticle) => $receptionReferenceArticle->getQuantiteAR() !== $receptionReferenceArticle->getQuantite())
                ->count() > 0;

        if ($isReceptionPartial) {
            $statusReceptionPartial = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_PARTIELLE);
            $reception->setStatut($statusReceptionPartial);
        } else {
            $statusReceptionTotal = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_TOTALE);
            $reception->setStatut($statusReceptionTotal);
        }
    }

    public function createTrackingAndStockMovementReception(EntityManagerInterface    $entityManager,
                                                            int                       $quantityReceived,
                                                            Article|ReferenceArticle  $article,
                                                            Reception                 $reception,
                                                            DateTime                  $now,
                                                            ReceptionReferenceArticle $receptionReferenceArticle): void
    {
        $currentUser = $this->userService->getUser();
        $stockMovement = $this->mouvementStockService->createMouvementStock(
            $currentUser,
            null,
            $quantityReceived,
            $article,
            MouvementStock::TYPE_ENTREE,
            [
                "from" => $reception,
                "locationTo" => $reception->getLocation(),
                "date" => $now,
            ]
        );
        $entityManager->persist($stockMovement);

        if ($article instanceof ReferenceArticle) {
            $createdMvt = $this->trackingMovementService->createTrackingMovement(
                $article->getTrackingPack() ?: $article->getBarCode(),
                $reception->getLocation(),
                $currentUser,
                $now,
                false,
                true,
                TrackingMovement::TYPE_DEPOSE,
                [
                    'mouvementStock' => $stockMovement,
                    'quantity' => $stockMovement->getQuantity(),
                    'from' => $reception,
                    'receptionReferenceArticle' => $receptionReferenceArticle,
                ]
            );
            $entityManager->persist($createdMvt);
        }
        else { // if ($article instanceof Article)
            // create drop tracking movements
            $this->trackingMovementService->persistLogisticUnitMovements(
                $entityManager,
                null,
                $reception->getLocation(),
                [$article],
                $currentUser,
                [
                    "mouvementStock" => $stockMovement,
                    "from" => $reception,
                    "trackingDate" => $now,
                    "refOrArticle" => $article,
                    "reception" => true,
                ]
            );
        }

        $receptionReferenceArticle->setQuantite($receptionReferenceArticle->getQuantite() + $quantityReceived);
    }

}
