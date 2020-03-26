<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\InventoryMission;
use App\Entity\MouvementStock;

use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\InventoryMissionRepository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Security\Core\Security;
use DateTime;

class InventoryService
{
	/**
	 * @var EntityManagerInterface
	 */
	private $em;

	/**
	 * @var ArticleRepository
	 */
    private $articleRepository;

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


    public function __construct(
    	InventoryEntryRepository $inventoryEntryRepository,
		Security $security,
		EntityManagerInterface $em,
		ArticleRepository $articleRepository,
		InventoryMissionRepository $inventoryMissionRepository
	)
    {
		$this->articleRepository = $articleRepository;
		$this->em = $em;
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
     * @param EntityManagerInterface $entityManager
     * @return bool
     * @throws NonUniqueResultException
     */
	public function doTreatAnomaly($idEntry, $reference, $isRef, $newQuantity, $comment, $user, EntityManagerInterface $entityManager)
	{
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
		$quantitiesAreEqual = true;

		if ($isRef) {
			$refOrArt = $referenceArticleRepository->findOneByReference($reference);
			$quantity = $refOrArt->getQuantiteStock();
		} else {
			$refOrArt = $this->articleRepository->findOneByReference($reference);
			$quantity = $refOrArt->getQuantite();
		}

		$diff = $newQuantity - $quantity;

		if ($diff != 0) {
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

			$entityManager->persist($mvt);
			$quantitiesAreEqual = false;
		}

		$entry = $this->inventoryEntryRepository->find($idEntry);
		$entry->setAnomaly(false);

		$refOrArt->setDateLastInventory(new DateTime('now'));

        $entityManager->flush();

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
