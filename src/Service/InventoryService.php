<?php

namespace App\Service;

use App\Entity\MouvementStock;

use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Security;
use DateTime;

class InventoryService
{
	/**
	 * @var EntityManagerInterface
	 */
	private $em;

	/**
	 * @var ReferenceArticleRepository
	 */
    private $referenceArticleRepository;

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


    public function __construct(InventoryEntryRepository $inventoryEntryRepository, Security $security, EntityManagerInterface $em, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository)
    {
		$this->referenceArticleRepository = $referenceArticleRepository;
		$this->articleRepository = $articleRepository;
		$this->em = $em;
		$this->user = $security->getUser();
		$this->inventoryEntryRepository = $inventoryEntryRepository;
    }

	/**
	 * @param int $idEntry
	 * @param string $reference
	 * @param bool $isRef
	 * @param int $newQuantity
	 * @param string $comment
	 * @param Utilisateur $user
	 * @return bool
	 * @throws NonUniqueResultException
	 */
	public function doTreatAnomaly($idEntry, $reference, $isRef, $newQuantity, $comment, $user)
	{
		$em = $this->em;
		$quantitiesAreEqual = true;

		if ($isRef) {
			$refOrArt = $this->referenceArticleRepository->findOneByReference($reference);
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

			$em->persist($mvt);
			$quantitiesAreEqual = false;
		}

		$entry = $this->inventoryEntryRepository->find($idEntry);
		$entry->setAnomaly(false);

		$refOrArt->setDateLastInventory(new DateTime('now'));

		$em->flush();

		return $quantitiesAreEqual;
	}
}
