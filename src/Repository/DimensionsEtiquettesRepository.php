<?php

namespace App\Repository;

use App\Entity\DimensionsEtiquettes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DimensionsEtiquettes|null find($id, $lockMode = null, $lockVersion = null)
 * @method DimensionsEtiquettes|null findOneBy(array $criteria, array $orderBy = null)
 * @method DimensionsEtiquettes[]    findAll()
 * @method DimensionsEtiquettes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DimensionsEtiquettesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DimensionsEtiquettes::class);
    }

	/**
	 * @return DimensionsEtiquettes|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
    public function findOneDimension()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT de
            FROM App\Entity\DimensionsEtiquettes de
            "
        );
        return $query->getOneOrNullResult();
    }

}
