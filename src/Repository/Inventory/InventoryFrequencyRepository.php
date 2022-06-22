<?php

namespace App\Repository\Inventory;

use App\Entity\Inventory\InventoryFrequency;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method InventoryFrequency|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryFrequency|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryFrequency[]    findAll()
 * @method InventoryFrequency[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryFrequencyRepository extends EntityRepository
{

    /**
     * @param string $label
     * @return mixed
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByLabel($label)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
            "SELECT count(ic)
            FROM App\Entity\InventoryFrequency ic
            WHERE ic.label = :label"
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

	/**
	 * @return InventoryFrequency[]
	 */
	public function findUsedByCat()
	{
		$em = $this->getEntityManager();

		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT if
			FROM App\Entity\InventoryFrequency if
			JOIN if.categories c"
		);

		return $query->execute();
	}

	public function getLabelBySearch($search) {
	    return $this->createQueryBuilder('inventoryFrequency')
            ->select('inventoryFrequency.label AS text')
            ->addSelect('inventoryFrequency.id AS id')
            ->where('inventoryFrequency.label LIKE :label')
            ->setParameter('label','%'.$search.'%')
            ->getQuery()
            ->getResult();
    }
}
