<?php

namespace App\Repository;

use App\Entity\CategorieStatut;
use Doctrine\ORM\EntityRepository;

/**
 * @method CategorieStatut|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategorieStatut|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategorieStatut[]    findAll()
 * @method CategorieStatut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieStatutRepository extends EntityRepository
{

	/**
	 * @param string $label
	 * @return CategorieStatut[]
	 */
    public function findByLabelLike($label)
	{
		$em = $this->getEntityManager();

		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT cs
			FROM App\Entity\CategorieStatut cs
			WHERE cs.nom LIKE :label
			")
		->setParameter('label', '%' . $label . '%');

		return $query->execute();
	}
}
