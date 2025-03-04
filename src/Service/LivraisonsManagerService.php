<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
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
use App\Service\Tracking\TrackingMovementService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;


/**
 * Class LivraisonsManagerService
 * @package App\Service
 */
class LivraisonsManagerService
{

    public const MOUVEMENT_DOES_NOT_EXIST_EXCEPTION = 'mouvement-does-not-exist';
    public const LIVRAISON_ALREADY_BEGAN = 'livraison-already-began';
    public const NATURE_NOT_ALLOWED = 1;

    #[Required]
    public NotificationService $notificationService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public MouvementStockService $mouvementStockService;

    #[Required]
    public RouterInterface $router;

    public function createLivraison(DateTime               $dateEnd,
                                    Preparation            $preparation,
                                    EntityManagerInterface $entityManager = null,
                                    string                 $statusCode = Livraison::STATUT_A_TRAITER): Livraison
    {
        if (!isset($entityManager)) {
            $entityManager = $this->entityManager;
        }
        $statutRepository = $entityManager->getRepository(Statut::class);
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ORDRE_LIVRAISON, $statusCode);
        $livraisonNumber = $this->generateNumber($dateEnd, $entityManager);

        $order = (new Livraison())
            ->setPreparation($preparation)
            ->setDate($dateEnd)
            ->setNumero($livraisonNumber)
            ->setStatut($statut);

        $entityManager->persist($order);

        return $order;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): self
    {
        $this->entityManager = $entityManager;
        return $this;
    }


    public function finishLivraison(Utilisateur  $user,
                                    Livraison    $livraison,
                                    DateTime     $dateEnd,
                                    ?Emplacement $nextLocation,
                                    array        $options = []): void {
        $pairings = $livraison->getPreparation()->getPairings();
        $pairingEnd = new DateTime('now');
        foreach ($pairings as $pairing) {
            if ($pairing->isActive()) {
                $pairing->setActive(false);
                $pairing->setEnd($pairingEnd);
            }
        }

        if ($livraison->getStatut()?->getCode() === Livraison::STATUT_A_TRAITER
            || $livraison->getUtilisateur()?->getId() === $user->getId()) {

            // repositories
            $statutRepository = $this->entityManager->getRepository(Statut::class);
            $movementRepository = $this->entityManager->getRepository(MouvementStock::class);

            $statutForLivraison = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::ORDRE_LIVRAISON,
                $livraison->getPreparation()->getStatut()?->getCode() === Preparation::STATUT_INCOMPLETE ? Livraison::STATUT_INCOMPLETE : Livraison::STATUT_LIVRE);

            $livraison
                ->setStatut($statutForLivraison)
                ->setUtilisateur($user)
                ->setDateFin($dateEnd);

            $demande = $livraison->getDemande();
            $demandeIsPartial = (
                $demande
                    ->getPreparations()
                    ->filter(function (Preparation $preparation) {
                        return $preparation->getStatut()?->getCode() === Preparation::STATUT_A_TRAITER ||
                            $preparation->getLivraison()?->getStatut()?->getCode() === Livraison::STATUT_A_TRAITER;
                    })
                    ->count()
                > 0
            );

            $statutLivre = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::DEM_LIVRAISON,
                $demandeIsPartial ? Demande::STATUT_LIVRE_INCOMPLETE : Demande::STATUT_LIVRE
            );
            $demande->setStatut($statutLivre);

            $preparation = $livraison->getPreparation();

            $inactiveArticleStatus = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);
            $articleLines = $preparation->getArticleLines();

            /** @var Pack[] $packs */
            $packs = stream::from($articleLines)
                ->filterMap(fn(PreparationOrderArticleLine $line) => $line->getArticle()->getCurrentLogisticUnit())
                ->unique()
                ->toArray();

            foreach ($packs as $pack) {
                $currentPackLocation = $pack->getLastOngoingDrop()?->getEmplacement();
                if (!$currentPackLocation) {
                    throw new FormException("L'unité logistique que vous souhaitez déplacer n'a pas d'emplacement initial. Vous devez déposer votre unité logistique sur un emplacement avant d'y déposer vos articles.");
                }

                $this->trackingMovementService->persistTrackingMovement(
                    $this->entityManager,
                    $pack,
                    $currentPackLocation,
                    $user,
                    $dateEnd,
                    true,
                    TrackingMovement::TYPE_PRISE,
                    false,
                    [
                        "delivery" => $livraison,
                        "stockAction" => true,
                    ]
                );
            }

            foreach ($articleLines as $articleLine) {
                $article = $articleLine->getArticle();
                $tracking = $this->trackingMovementService->persistTrackingMovement(
                    $this->entityManager,
                    $article->getTrackingPack() ?? $article->getBarCode(),
                    $article->getEmplacement(),
                    $user,
                    $dateEnd,
                    true,
                    TrackingMovement::TYPE_PRISE,
                    false,
                    [
                        "delivery" => $livraison,
                        "stockAction" => true,
                    ]
                );

                $pickingMovement = $tracking["movement"];

                $tracking = $this->trackingMovementService->persistTrackingMovement(
                    $this->entityManager,
                    $pickingMovement->getPack(), // same pack of picking
                    $nextLocation,
                    $user,
                    $dateEnd,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    false,
                    [
                        "delivery" => $livraison,
                        "stockAction" => true,
                    ]
                );

                if($tracking['success']){
                    $dropMovement = $tracking["movement"];
                } else {
                    throw new Exception($tracking['msg'], self::NATURE_NOT_ALLOWED);
                }

                if ($articleLine->getPack()) {
                    $pickingMovement->setLogisticUnitParent($articleLine->getPack());
                    $dropMovement->setLogisticUnitParent($articleLine->getPack());
                }
            }

            foreach ($packs as $pack) {
                $dropMovement = $this->trackingMovementService->persistTrackingMovement(
                    $this->entityManager,
                    $pack,
                    $nextLocation,
                    $user,
                    $dateEnd,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    false,
                    [
                        "delivery" => $livraison,
                        "stockAction" => true,
                    ]
                );
                $pack
                    ->setLastOngoingDrop($dropMovement['movement'])
                    ->setLastAction($dropMovement['movement']);
            }

            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($articleLines as $articleLine) {
                $article = $articleLine->getArticle();
                $article
                    ->setStatut($inactiveArticleStatus)
                    ->setEmplacement($nextLocation);
            }

            $referenceLines = $preparation->getReferenceLines();

            /** @var PreparationOrderReferenceLine $referenceLine */
            foreach ($referenceLines as $referenceLine) {
                $pickedQuantity = $referenceLine->getPickedQuantity();
                $reference = $referenceLine->getReference();

                if ($reference->getTypeQuantite() == ReferenceArticle::QUANTITY_TYPE_REFERENCE
                    && !empty($pickedQuantity)) {
                    $newQuantiteStock = (($reference->getQuantiteStock() ?? 0) - $pickedQuantity);
                    $newQuantiteReservee = (($reference->getQuantiteReservee() ?? 0) - $pickedQuantity);

                    if ($newQuantiteStock >= 0
                        && $newQuantiteReservee >= 0
                        && $newQuantiteStock >= $newQuantiteReservee) {
                        $reference->setQuantiteStock($newQuantiteStock);
                        $reference->setQuantiteReservee($newQuantiteReservee);
                    } else {
                        throw new NegativeQuantityException($reference);
                    }
                }

                $tracking = $this->trackingMovementService->persistTrackingMovement(
                    $this->entityManager,
                    $reference->getTrackingPack() ?: $reference->getBarCode(),
                    $preparation->getEndLocation(),
                    $user,
                    $dateEnd,
                    true,
                    TrackingMovement::TYPE_PRISE,
                    false,
                    [
                        "refOrArticle" => $reference,
                        "delivery" => $livraison,
                        "stockAction" => true,
                    ],
                );

                $pickingMovement = $tracking["movement"];

                $this->trackingMovementService->persistTrackingMovement(
                    $this->entityManager,
                    $pickingMovement->getPack(),
                    $nextLocation,
                    $user,
                    $dateEnd,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    false,
                    [
                        "refOrArticle" => $reference,
                        "delivery" => $livraison,
                        "stockAction" => true,
                    ],
                );
            }

            // on termine les mouvements de livraison
            $movements = $movementRepository->findBy(['livraisonOrder' => $livraison]);
            foreach ($movements as $pickingMovement) {
                $this->mouvementStockService->updateMovementDates($pickingMovement, $dateEnd);
                if (isset($nextLocation)) {
                    $pickingMovement->setEmplacementTo($nextLocation);
                }
            }

            $title = $this->translation->translate('Général', null, 'Header', 'Wiilog', false) .
                MailerService::OBJECT_SERPARATOR .
                ( $demandeIsPartial
                    ? $this->translation->translate("Ordre", "Livraison", "Livraison", false) . ' effectuée partiellement'
                    : $this->translation->translate("Ordre", "Livraison", "Livraison", false) . ' effectuée'
                );
            $bodyTitle = $demandeIsPartial ? 'La demande a été livrée partiellement.' : 'La demande a bien été livrée.';

            $sendMailCallback = function(array $to) use ($title, $demande, $preparation, $bodyTitle, $nextLocation): void {
                $this->mailerService->sendMail(
                    $title,
                    $this->templating->render('mails/contents/mailLivraisonDone.html.twig', [
                        'request' => $demande,
                        'preparation' => $preparation,
                        'title' => $bodyTitle,
                        'dropLocation' => $nextLocation,
                        "urlSuffix" => $this->router->generate("demande_show", [
                            "id" => $demande->getId(),
                        ]),
                    ]),
                    $to
                );
            };

            if(!empty($options['deliveryStationLineReceivers'])) {
                $sendMailCallback($options['deliveryStationLineReceivers']);
            }

            if($demande->getType()->getSendMailRequester() || $demande->getType()->getSendMailReceiver()) {
                $to = [];
                if ($demande->getType()->getSendMailRequester()) {
                    $to[] = $demande->getUtilisateur();
                }
                if ($demande->getType()->getSendMailReceiver() && $demande->getReceiver()) {
                    $to[] = $demande->getReceiver();
                }

                $sendMailCallback($to);
            }
        } else {
            throw new Exception(self::LIVRAISON_ALREADY_BEGAN);
        }
    }

    public function resetStockMovementsOnDelete(Livraison $livraison,
                                                Emplacement $destination,
                                                Utilisateur $user,
                                                EntityManagerInterface $entityManager): void
    {

        $movements = $livraison->getMouvements()->toArray();
        /** @var MouvementStock $movement */
        foreach ($movements as $movement) {
            $movement->setLivraisonOrder(null);
        }

        $statutRepository = $entityManager->getRepository(Statut::class);

        $now = new DateTime('now');
        $statutTransit = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
        $preparation = $livraison->getpreparation();
        $livraisonStatus = $livraison->getStatut();
        $livraisonStatusCode = $livraisonStatus?->getCode();
        if($livraisonStatusCode === Livraison::STATUT_A_TRAITER) {
            $movementType = MouvementStock::TYPE_TRANSFER;
            $movementsToDelete = $livraison
                ->getMouvements()
                ->filter(fn(MouvementStock $movement) => (
                    !$movement->getDate() && $movement->getType() === MouvementStock::TYPE_SORTIE
                ));

            foreach ($movementsToDelete as $movement) {
                $entityManager->remove($movement);
            }
        } else {
            $movementType = MouvementStock::TYPE_ENTREE;
        }

        $livraison->getMouvements()->clear();
        $articleLines = $preparation->getArticleLines();

        /** @var PreparationOrderArticleLine $articleLine */
        foreach ($articleLines as $articleLine) {
            $article = $articleLine->getArticle();
            $pickedQuantity = $articleLine->getPickedQuantity();
            if (!empty($pickedQuantity)) {
                $this->resetStockMovementOnDeleteForArticle(
                    $user,
                    $article,
                    $article->getEmplacement(),
                    $destination,
                    $pickedQuantity,
                    $now,
                    $entityManager,
                    $movementType
                );
                $article
                    ->setStatut($statutTransit)
                    ->setEmplacement($destination);
            }
        }

        $referenceLines = $preparation->getReferenceLines();

        $demande = $livraison->getDemande();
        $livraisonDestination = isset($demande) ? $demande->getDestination() : null;

        /** @var PreparationOrderReferenceLine $referenceLine */
        foreach ($referenceLines as $referenceLine) {
            $pickedQuantity = $referenceLine->getPickedQuantity();
            $refArticle = $referenceLine->getReference();
            if (!empty($pickedQuantity)) {
                if ($livraison->isCompleted()) {
                    $newQuantiteStock = (($refArticle->getQuantiteStock() ?? 0) + $pickedQuantity);
                    $newQuantiteReservee = (($refArticle->getQuantiteReservee() ?? 0) + $pickedQuantity);
                    $refArticle->setQuantiteStock($newQuantiteStock);
                    $refArticle->setQuantiteReservee($newQuantiteReservee);
                }
                if (isset($livraisonDestination)) {
                    $this->resetStockMovementOnDeleteForArticle(
                        $user,
                        $refArticle,
                        $livraisonDestination,
                        $destination,
                        $pickedQuantity,
                        $now,
                        $entityManager,
                        $movementType
                    );
                }
            }
        }
    }

    private function resetStockMovementOnDeleteForArticle(Utilisateur $user,
                                                          $article,
                                                          Emplacement $from,
                                                          Emplacement $destination,
                                                          int $quantity,
                                                          DateTime $date,
                                                          EntityManagerInterface $entityManager,
                                                          string $movementType): void
    {
        $mouvementStock = $this->mouvementStockService->createMouvementStock(
            $user,
            $from,
            $quantity,
            $article,
            $movementType
        );

        $this->mouvementStockService->finishStockMovement(
            $mouvementStock,
            $date,
            $destination
        );

        $entityManager->persist($mouvementStock);
    }

    public function generateNumber(DateTime $date, EntityManagerInterface $entityManager): string
    {
        $livraisonRepository = $entityManager->getRepository(Livraison::class);

        $livraisonNumber = ('L-' . $date->format('YmdHis'));
        $livraisonWithSameNumber = $livraisonRepository->countByNumero($livraisonNumber);
        $livraisonWithSameNumber++;

        $currentCounterStr = $livraisonWithSameNumber < 10
            ? ('0' . $livraisonWithSameNumber)
            : $livraisonWithSameNumber;

        return ($livraisonNumber . '-' . $currentCounterStr);
    }

}
