<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Exceptions\NegativeQuantityException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
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

    #[Required]
    public NotificationService $notificationService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    private $entityManager;
    private $mailerService;
    private $templating;
    private $mouvementStockService;

    public function __construct(EntityManagerInterface $entityManager,
                                MouvementStockService $mouvementStockService,
                                MailerService $mailerService,
                                Twig_Environment $templating)
    {
        $this->entityManager = $entityManager;
        $this->mailerService = $mailerService;
        $this->templating = $templating;
        $this->mouvementStockService = $mouvementStockService;
    }

    /**
     * @param DateTime $dateEnd
     * @param Preparation $preparation
     * @param EntityManagerInterface|null $entityManager
     * @return Livraison
     */
    public function createLivraison(DateTime $dateEnd,
                                    Preparation $preparation,
                                    EntityManagerInterface $entityManager = null)
    {
        if (!isset($entityManager)) {
            $entityManager = $this->entityManager;
        }
        $statutRepository = $entityManager->getRepository(Statut::class);
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_A_TRAITER);

        $entityManager->getRepository(Livraison::class);

        $livraison = new Livraison();

        $livraisonNumber = $this->generateNumber($dateEnd, $entityManager);

        $livraison
            ->setPreparation($preparation)
            ->setDate($dateEnd)
            ->setNumero($livraisonNumber)
            ->setStatut($statut);

        $entityManager->persist($livraison);

        return $livraison;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): self
    {
        $this->entityManager = $entityManager;
        return $this;
    }


    public function finishLivraison(Utilisateur $user,
                                    Livraison $livraison,
                                    DateTime $dateEnd,
                                    ?Emplacement $nextLocation): void
    {
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

            $packs = stream::from($articleLines)
                ->map(fn(PreparationOrderArticleLine $line) => $line->getArticle()->getCurrentLogisticUnit())
                ->filter()
                ->unique()
                ->toArray();

            foreach ($packs as $pack) {
                $this->trackingMovementService->persistTrackingMovement(
                    $this->entityManager,
                    $pack,
                    $pack->getLastDrop()->getEmplacement(),
                    $user,
                    $dateEnd,
                    true,
                    TrackingMovement::TYPE_PRISE,
                    false,
                    ['delivery' => $livraison]
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
                    ['delivery' => $livraison]
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
                    ['delivery' => $livraison]
                );

                $dropMovement = $tracking["movement"];

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
                    ['delivery' => $livraison]
                );
                $pack
                    ->setLastDrop($dropMovement['movement'])
                    ->setLastTracking($dropMovement['movement']);
            }

            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($articleLines as $articleLine) {
                $article = $articleLine->getArticle();
                $article
                    ->setStatut($inactiveArticleStatus)
                    ->setEmplacement($demande->getDestination());
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
            }

            // on termine les mouvements de livraison
            $movements = $movementRepository->findBy(['livraisonOrder' => $livraison]);
            foreach ($movements as $pickingMovement) {
                $pickingMovement->setDate($dateEnd);
                if (isset($nextLocation)) {
                    $pickingMovement->setEmplacementTo($nextLocation);
                }
            }

            $title = $demandeIsPartial ? 'FOLLOW GT // Livraison effectuée partiellement' : 'FOLLOW GT // Livraison effectuée';
            $bodyTitle = $demandeIsPartial ? 'Votre demande a été livrée partiellement.' : 'Votre demande a bien été livrée.';

            if ($livraison->getDemande()->getType()->getSendMailRequester()) {
                $this->mailerService->sendMail(
                    $title,
                    $this->templating->render('mails/contents/mailLivraisonDone.html.twig', [
                        'request' => $demande,
                        'preparation' => $preparation,
                        'title' => $bodyTitle,
                    ]),
                    $demande->getUtilisateur()
                );
            }

            $title = $demandeIsPartial ? 'FOLLOW GT // Livraison effectuée partiellement' : 'FOLLOW GT // Livraison effectuée';
            $bodyTitle = $demandeIsPartial ? 'Une demande vous concernant a été livrée partiellement.' : 'Une demande vous concernant a bien été livrée.';

            if ($livraison->getDemande()->getType()->getSendMailReceiver()
                && $demande->getDestinataire()
                && !($demande->getType()->getSendMailRequester() && $demande->getUtilisateur()->getId() === $demande->getDestinataire()->getId())) {
                $this->mailerService->sendMail(
                    $title,
                    $this->templating->render('mails/contents/mailLivraisonDone.html.twig', [
                        'request' => $demande,
                        'preparation' => $preparation,
                        'title' => $bodyTitle,
                    ]),
                    $demande->getDestinataire()
                );
            }
        }
        else {
            throw new Exception(self::LIVRAISON_ALREADY_BEGAN);
        }
    }

    /**
     * @param Livraison $livraison
     * @param Emplacement $destination
     * @param Utilisateur $user
     * @param EntityManagerInterface $entityManager
     * @throws NonUniqueResultException
     */
    public function resetStockMovementsOnDelete(Livraison $livraison,
                                                Emplacement $destination,
                                                Utilisateur $user,
                                                EntityManagerInterface $entityManager)
    {

        $movements = $livraison->getMouvements()->toArray();
        /** @var MouvementStock $movement */
        foreach ($movements as $movement) {
            $movement->setLivraisonOrder(null);
        }
        $livraison->getMouvements()->clear();

        $statutRepository = $entityManager->getRepository(Statut::class);

        $now = new DateTime('now');
        $statutTransit = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
        $preparation = $livraison->getpreparation();
        $livraisonStatus = $livraison->getStatut();
        $livraisonStatusCode = $livraisonStatus?->getCode();
        $movementType = ($livraisonStatusCode === Livraison::STATUT_A_TRAITER)
            ? MouvementStock::TYPE_TRANSFER
            : MouvementStock::TYPE_ENTREE;

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

    /**
     * @param Utilisateur $user
     * @param Article|ReferenceArticle $article
     * @param Emplacement $from
     * @param Emplacement $destination
     * @param int $quantity
     * @param DateTime $date
     * @param EntityManagerInterface $entityManager
     * @param string $movementType
     */
    private function resetStockMovementOnDeleteForArticle(Utilisateur $user,
                                                          $article,
                                                          Emplacement $from,
                                                          Emplacement $destination,
                                                          int $quantity,
                                                          DateTime $date,
                                                          EntityManagerInterface $entityManager,
                                                          string $movementType)
    {
        $mouvementStock = $this->mouvementStockService->createMouvementStock(
            $user,
            $from,
            $quantity,
            $article,
            $movementType
        );

        $this->mouvementStockService->finishMouvementStock(
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
