<?php

namespace App\Repository;

use App\Entity\LitigeHistoric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method LitigeHistoric|null find($id, $lockMode = null, $lockVersion = null)
 * @method LitigeHistoric|null findOneBy(array $criteria, array $orderBy = null)
 * @method LitigeHistoric[]    findAll()
 * @method LitigeHistoric[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LitigeHistoricRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, LitigeHistoric::class);
    }

    public function findByLitigeId($litige)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT h
			FROM App\Entity\litigeHistoric h
			WHERE h.litige = :litigeId"
        )->setParameter('litigeId', $litige);

        return $query->execute();
    }
}
