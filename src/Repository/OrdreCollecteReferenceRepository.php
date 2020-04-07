<?php

namespace App\Repository;

use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;


/**
 * @method OrdreCollecteReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdreCollecteReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdreCollecteReference[]    findAll()
 * @method OrdreCollecteReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdreCollecteReferenceRepository extends EntityRepository
{

	/**
	 * @param OrdreCollecte $collecte
	 * @param int $refId
	 * @return OrdreCollecteReference
	 * @throws NonUniqueResultException
	 */
    public function findByOrdreCollecteAndRefId($collecte, $refId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT ocr
            FROM App\Entity\OrdreCollecteReference ocr
            WHERE ocr.ordreCollecte = :collecte
            AND ocr.referenceArticle = :ref
            '
		)->setParameters([
			'collecte' => $collecte,
			'ref' => $refId
		]);
		return $query->getOneOrNullResult();
	}

	/**
	 * @param OrdreCollecte|int $ordreCollecte
	 * @return OrdreCollecteReference[]|null
	 */
	public function findByOrdreCollecte($ordreCollecte)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT ocr
          FROM App\Entity\OrdreCollecteReference ocr
          WHERE ocr.ordreCollecte = :ordreCollecte"
		)->setParameter('ordreCollecte',  $ordreCollecte);

		return $query->execute();
	}
}
