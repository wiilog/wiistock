<?php

namespace App\Repository;

use App\Entity\InventoryCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InventoryCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryCategory[]    findAll()
 * @method InventoryCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryCategory::class);
    }

    /**
     * @param string $label
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByLabel($label)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        	/** @lang DQL */
            "SELECT count(ic)
            FROM App\Entity\InventoryCategory ic
            WHERE ic.label = :label"
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

    public function countByLabelDiff($label, $categoryLabel)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        	/** @lang DQL */
            "SELECT count(ic)
            FROM App\Entity\InventoryCategory ic
            WHERE ic.label = :label AND ic.label != :categoryLabel"
        )->setParameters([
            'label' => $label,
            'categoryLabel' => $categoryLabel
        ]);

        return $query->getSingleScalarResult();
    }

    public function countByFrequency($frequency)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(c)
            FROM App\Entity\InventoryCategory c
            WHERE c.frequency = :frequency"
        )->setParameter('frequency', $frequency);

        return $query->getSingleScalarResult();
    }
}
