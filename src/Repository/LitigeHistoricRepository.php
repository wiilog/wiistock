<?php

namespace App\Repository;

use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method LitigeHistoric|null find($id, $lockMode = null, $lockVersion = null)
 * @method LitigeHistoric|null findOneBy(array $criteria, array $orderBy = null)
 * @method LitigeHistoric[]    findAll()
 * @method LitigeHistoric[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LitigeHistoricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LitigeHistoric::class);
    }

	/**
	 * @param Litige|int $litige
	 * @return LitigeHistoric[]
	 */
    public function findByLitige($litige)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT h
			FROM App\Entity\litigeHistoric h
			WHERE h.litige = :litige"
        )->setParameter('litige', $litige);

        return $query->execute();
    }
}
