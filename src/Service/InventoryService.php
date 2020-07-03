<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Demande;
use App\Entity\InventoryEntry;
use App\Entity\InventoryMission;
use App\Entity\LigneArticle;
use App\Entity\MouvementStock;

use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\DemandeToTreatExistsException;
use App\Repository\InventoryEntryRepository;
use App\Repository\InventoryMissionRepository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Component\Security\Core\Security;
use DateTime;

class InventoryService
{
	/**
	 * @var EntityManagerInterface
	 */
	private $entityManager;

	/**
	 * @var
	 */
    private $user;

	/**
	 * @var InventoryEntryRepository
	 */
    private $inventoryEntryRepository;

	/**
	 * @var InventoryMissionRepository
	 */
    private $inventoryMissionRepository;


    public function __construct(InventoryEntryRepository $inventoryEntryRepository,
                                Security $security,
                                EntityManagerInterface $entityManager,
                                InventoryMissionRepository $inventoryMissionRepository) {
		$this->entityManager = $entityManager;
		$this->user = $security->getUser();
		$this->inventoryEntryRepository = $inventoryEntryRepository;
		$this->inventoryMissionRepository = $inventoryMissionRepository;
    }

    /**
     * @param int $idEntry
     * @param string $reference
     * @param bool $isRef
     * @param int $newQuantity
     * @param string $comment
     * @param Utilisateur $user
     * @return bool
     * @throws DemandeToTreatExistsException
     * @throws ArticleNotAvailableException
     */
	public function doTreatAnomaly($idEntry, $reference, $isRef, $newQuantity, $comment, $user)
	{
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);

        $quantitiesAreEqual = true;

        $isDemandeToTreat = function (Demande $demande) {
            $demandeStatus = $demande->getStatut();
            $demandeStatusName = $demandeStatus->getNom();
            return (
                $demandeStatusName === Demande::STATUT_A_TRAITER
                || $demandeStatusName === Demande::STATUT_PREPARE
            );
        };

		if ($isRef) {
			$refOrArt = $referenceArticleRepository->findOneByReference($reference);
			$quantity = $refOrArt->getQuantiteStock();
		} else {
		    /** @var Article $refOrArt */
			$refOrArt = $articleRepository->findOneByReference($reference);
			$quantity = $refOrArt->getQuantite();
		}

		$diff = $newQuantity - $quantity;

		if ($diff != 0) {
            if ($isRef) {
                $demandeToTreatCounter = $refOrArt
                    ->getLigneArticles()
                    ->filter(function(LigneArticle $ligneArticle) use ($isDemandeToTreat) {
                        $demande = $ligneArticle->getDemande();
                        return $isDemandeToTreat($demande);
                    })
                    ->count();
            }
            else {
                $demande = $refOrArt->getDemande();

                if ($refOrArt->getStatut()->getNom() !== Article::STATUT_ACTIF) {
                    throw new ArticleNotAvailableException();
                }

                $demandeToTreatCounter = $isDemandeToTreat($demande) ? 1 : 0;
            }

            if ($demandeToTreatCounter > 0) {
                throw new DemandeToTreatExistsException();
            }

			$mvt = new MouvementStock();
			$mvt
				->setUser($user)
				->setDate(new \DateTime('now'))
				->setComment($comment)
				->setQuantity(abs($diff));

			$emplacement = $refOrArt->getEmplacement();
			$mvt->setEmplacementFrom($emplacement);
			$mvt->setEmplacementTo($emplacement);
			if ($isRef) {
				$mvt->setRefArticle($refOrArt);
				//TODO à supprimer quand la quantité sera calculée directement via les mouvements de stock
				$refOrArt->setQuantiteStock($newQuantity);
			} else {
				$mvt->setArticle($refOrArt);
				//TODO à supprimer quand la quantité sera calculée directement via les mouvements de stock
				$refOrArt->setQuantite($newQuantity);
			}

			$typeMvt = $diff < 0 ? MouvementStock::TYPE_INVENTAIRE_SORTIE : MouvementStock::TYPE_INVENTAIRE_ENTREE;
			$mvt->setType($typeMvt);

			$this->entityManager->persist($mvt);
			$quantitiesAreEqual = false;
		}

		$entry = $inventoryEntryRepository->find($idEntry);
		$entry
            ->setQuantity($newQuantity)
            ->setAnomaly(false);

		$refOrArt->setDateLastInventory(new DateTime('now'));

        $this->entityManager->flush();

		return $quantitiesAreEqual;
	}

	/**
	 * @param ReferenceArticle|Article $refOrArticle
	 * @param InventoryMission $mission
	 * @param boolean $isRef
	 * @return boolean
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function isInMissionInSamePeriod($refOrArticle, $mission, $isRef) {

	    $beginDate = clone($mission->getStartPrevDate())->setTime(0, 0, 0);
	    $endDate = clone($mission->getEndPrevDate())->setTime(23, 59, 59);
		if ($isRef) {
			$nbMissions = $this->inventoryMissionRepository->countByRefAndDates($refOrArticle, $beginDate, $endDate);
		} else {
			$nbMissions = $this->inventoryMissionRepository->countByArtAndDates($refOrArticle, $beginDate, $endDate);
		}

		return $nbMissions > 0;
	}
}
