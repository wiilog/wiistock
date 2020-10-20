<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Exceptions\NegativeQuantityException;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;


/**
 * Class LivraisonsManagerService
 * @package App\Service
 */
class LivraisonsManagerService
{

    public const MOUVEMENT_DOES_NOT_EXIST_EXCEPTION = 'mouvement-does-not-exist';
    public const LIVRAISON_ALREADY_BEGAN = 'livraison-already-began';


    private $entityManager;
    private $mailerService;
    private $templating;
    private $mouvementStockService;

    /**
     * LivraisonsManagerService constructor.
     * @param EntityManagerInterface $entityManager
     * @param MouvementStockService $mouvementStockService
     * @param MailerService $mailerService
     * @param Twig_Environment $templating
     */
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

        return $livraison;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): self
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * @param Utilisateur $user
     * @param Livraison $livraison
     * @param DateTime $dateEnd
     * @param Emplacement|null $emplacementTo
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     * @throws NegativeQuantityException
     * @throws Exception
     */
    public function finishLivraison(Utilisateur $user,
                                    Livraison $livraison,
                                    DateTime $dateEnd,
                                    ?Emplacement $emplacementTo): void
    {
        if (($livraison->getStatut() && $livraison->getStatut()->getNom() === Livraison::STATUT_A_TRAITER) ||
            $livraison->getUtilisateur() && ($livraison->getUtilisateur()->getId() === $user->getId())) {

            // repositories
            $statutRepository = $this->entityManager->getRepository(Statut::class);
            $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);

            $statutForLivraison = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::ORDRE_LIVRAISON,
                $livraison->getPreparation()->getStatut()->getNom() === Preparation::STATUT_INCOMPLETE ? Livraison::STATUT_INCOMPLETE : Livraison::STATUT_LIVRE);

            $livraison
                ->setStatut($statutForLivraison)
                ->setUtilisateur($user)
                ->setDateFin($dateEnd);

            $demande = $livraison->getDemande();
            $demandeIsPartial = (
                $demande
                    ->getPreparations()
                    ->filter(function (Preparation $preparation) {
                        return $preparation->getStatut()->getNom() === Preparation::STATUT_A_TRAITER;
                    })
                    ->count()
                > 0
            );
            foreach ($demande->getPreparations() as $preparation) {
                if ($preparation->getLivraison() &&
                    ($preparation->getLivraison()->getStatut()->getNom() === Livraison::STATUT_A_TRAITER)) {
                    $demandeIsPartial = true;
                    break;
                }
            }
            $statutLivre = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::DEM_LIVRAISON, $demandeIsPartial ? Demande::STATUT_LIVRE_INCOMPLETE : Demande::STATUT_LIVRE);
            $demande->setStatut($statutLivre);

            $preparation = $livraison->getPreparation();

            // quantités gérées à l'article
            $articles = $preparation->getArticles();
            foreach ($articles as $article) {
                $article
                    ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF))
                    ->setEmplacement($demande->getDestination());
            }

            // quantités gérées à la référence
            $ligneArticles = $preparation->getLigneArticlePreparations();

            /** @var LigneArticlePreparation $ligneArticle */
            foreach ($ligneArticles as $ligneArticle) {
                $pickedQuantity = $ligneArticle->getQuantitePrelevee();
                $refArticle = $ligneArticle->getReference();
                if (!empty($pickedQuantity)) {
                    $newQuantiteStock = (($refArticle->getQuantiteStock() ?? 0) - $pickedQuantity);
                    $newQuantiteReservee = (($refArticle->getQuantiteReservee() ?? 0) - $pickedQuantity);

                    if ($newQuantiteStock >= 0
                        && $newQuantiteReservee >= 0
                        && $newQuantiteStock >= $newQuantiteReservee) {
                        $refArticle->setQuantiteStock($newQuantiteStock);
                        $refArticle->setQuantiteReservee($newQuantiteReservee);
                    }
                    else {
                        throw new NegativeQuantityException($refArticle);
                    }
                }
            }

            // on termine les mouvements de livraison
            $mouvements = $mouvementRepository->findByLivraison($livraison);

            foreach ($mouvements as $mouvement) {
                $mouvement->setDate($dateEnd);
                if (isset($emplacementTo)) {
                    $mouvement->setEmplacementTo($emplacementTo);
                }
            }

            $this->mailerService->sendMail(
                'FOLLOW GT // Livraison effectuée',
                $this->templating->render('mails/contents/mailLivraisonDone.html.twig', [
                    'livraison' => $demande,
                    'title' => 'Votre demande a bien été livrée.',
                ]),
                $demande->getUtilisateur()->getMainAndSecondaryEmails()
            );
        } else {
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
                                                EntityManagerInterface $entityManager) {

        $movements = $livraison->getMouvements()->toArray();
        /** @var MouvementStock $movement */
        foreach ($movements as $movement) {
            $movement->setLivraisonOrder(null);
        }
        $livraison->getMouvements()->clear();

        $statutRepository = $entityManager->getRepository(Statut::class);

        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $statutTransit = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
        $preparation = $livraison->getpreparation();
        $livraisonStatus = $livraison->getStatut();
        $livraisonStatusName = $livraisonStatus->getNom();

        foreach ($preparation->getArticles() as $article) {
            $pickedQuantity = $article->getQuantite();
            if (!empty($pickedQuantity)) {
                $this->resetStockMovementOnDeleteForArticle(
                    $user,
                    $article,
                    $article->getEmplacement(),
                    $destination,
                    $pickedQuantity,
                    $now,
                    $entityManager,
                    (
                        ($livraisonStatusName === Livraison::STATUT_A_TRAITER)
                            ? MouvementStock::TYPE_TRANSFER
                            : MouvementStock::TYPE_ENTREE
                    )
                );
                $article
                    ->setStatut($statutTransit)
                    ->setEmplacement($destination);
            }
        }

        $ligneArticles = $preparation->getLigneArticlePreparations();

        $demande = $livraison->getDemande();
        $livraisonDestination = isset($demande) ? $demande->getDestination() : null;

        /** @var LigneArticlePreparation $ligneArticle */
        foreach ($ligneArticles as $ligneArticle) {
            $pickedQuantity = $ligneArticle->getQuantitePrelevee();
            $refArticle = $ligneArticle->getReference();
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
                        (
                            ($livraisonStatusName === Livraison::STATUT_A_TRAITER)
                                ? MouvementStock::TYPE_TRANSFER
                                : MouvementStock::TYPE_ENTREE
                        )
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
                                                          string $movementType) {
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

    public function generateNumber(DateTime $date, EntityManagerInterface $entityManager): string {
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
