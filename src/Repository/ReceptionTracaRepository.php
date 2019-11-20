<?php

namespace App\Repository;

use App\Entity\ReceptionTraca;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ReceptionTraca|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionTraca|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionTraca[]    findAll()
 * @method ReceptionTraca[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionTracaRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ReceptionTraca::class);
    }

    /**
     * @param $firstDay
     * @param $lastDay
     * @return mixed
     * @throws \Exception
     */
    public function countByDays($firstDay, $lastDay) {
        $from = new \DateTime(str_replace("/", "-", $firstDay) ." 00:00:00");
        $to   = new \DateTime(str_replace("/", "-", $lastDay) ." 23:59:59");
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(r.id) as count, r.dateCreation as date
			FROM App\Entity\ReceptionTraca r
			WHERE r.dateCreation BETWEEN :firstDay AND :lastDay
			GROUP BY r.dateCreation"
        )->setParameters([
            'lastDay' => $to,
            'firstDay' => $from
        ]);
        return $query->execute();
    }
}
