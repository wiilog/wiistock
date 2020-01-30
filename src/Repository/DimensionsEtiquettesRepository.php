<?php

namespace App\Repository;

use App\Entity\DimensionsEtiquettes;
use App\Entity\ParametrageGlobal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DimensionsEtiquettes|null find($id, $lockMode = null, $lockVersion = null)
 * @method DimensionsEtiquettes|null findOneBy(array $criteria, array $orderBy = null)
 * @method DimensionsEtiquettes[]    findAll()
 * @method DimensionsEtiquettes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DimensionsEtiquettesRepository extends ServiceEntityRepository
{
	private $parametrageGlobalRepository;

    public function __construct(
    	ManagerRegistry $registry,
		ParametrageGlobalRepository $parametrageGlobalRepository
	)
    {
        parent::__construct($registry, DimensionsEtiquettes::class);
        $this->parametrageGlobalRepository = $parametrageGlobalRepository;
    }

	/**
	 * @return DimensionsEtiquettes|null
	 * @throws NonUniqueResultException
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
