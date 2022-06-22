<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryMission;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Security\Core\Security;

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


    public function __construct(Security $security,
                                EntityManagerInterface $entityManager) {
		$this->entityManager = $entityManager;
		$this->user = $security->getUser();
    }

    /**
     * @param int $idEntry
     * @param $barCode
     * @param bool $isRef
     * @param int $newQuantity
     * @param string $comment
     * @param Utilisateur $user
     * @return bool
     * @throws ArticleNotAvailableException
     * @throws RequestNeedToBeProcessedException
     */
	public function doTreatAnomaly($idEntry, $barCode, $isRef, $newQuantity, $comment, $user)
	{
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);

        $quantitiesAreEqual = true;

		if ($isRef) {
			$refOrArt = $referenceArticleRepository->findOneBy(['barCode' => $barCode])
                ?: $referenceArticleRepository->findOneBy(['reference' => $barCode]);
			$quantity = $refOrArt->getQuantiteStock();
		} else {
		    /** @var Article $refOrArt */
            $refOrArt = $articleRepository->findOneBy(['barCode' => $barCode]) ?: $articleRepository->findOneByReference($barCode);
			$quantity = $refOrArt->getQuantite();
		}

		$diff = ($newQuantity - $quantity);

		if ($diff != 0) {
		    $statusRequired = $isRef ? [ReferenceArticle::STATUT_ACTIF] : [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE];
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
        $allEntriesToTreat = $inventoryEntryRepository->findBy([
            'refArticle' => $entry->getRefArticle(),
            'article' => $entry->getArticle(),
            'anomaly' => true
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
		    'quantitiesAreEqual' => $quantitiesAreEqual
        ];
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


        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);
	    $beginDate = clone($mission->getStartPrevDate())->setTime(0, 0, 0);
	    $endDate = clone($mission->getEndPrevDate())->setTime(23, 59, 59);
		if ($isRef) {
			$nbMissions = $inventoryMissionRepository->countByRefAndDates($refOrArticle, $beginDate, $endDate);
		} else {
			$nbMissions = $inventoryMissionRepository->countByArtAndDates($refOrArticle, $beginDate, $endDate);
		}

		return $nbMissions > 0;
	}
}
