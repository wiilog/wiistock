<?php

namespace App\Repository;

use App\Entity\ParametrageGlobal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ParametrageGlobal|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParametrageGlobal|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParametrageGlobal[]    findAll()
 * @method ParametrageGlobal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParametrageGlobalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParametrageGlobal::class);
    }

    /**
     * @param $label
     * @return ParametrageGlobal
     * @throws NonUniqueResultException
     */
    public function findOneByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT pg
            FROM App\Entity\ParametrageGlobal pg
            WHERE pg.label LIKE :label
            "
        )->setParameter('label', $label);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param $label
	 * @return ParametrageGlobal
	 * @throws NonUniqueResultException
	 */
    public function getOneParamByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT pg.value
            FROM App\Entity\ParametrageGlobal pg
            WHERE pg.label LIKE :label
            "
        )->setParameter('label', $label);

        $result = $query->getOneOrNullResult();

        return $result ? $result['value'] : null;
    }
}
