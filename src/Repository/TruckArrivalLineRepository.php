<?php

namespace App\Repository;

use App\Entity\TruckArrivalLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method TruckArrivalLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method TruckArrivalLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method TruckArrivalLine[]    findAll()
 * @method TruckArrivalLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TruckArrivalLineRepository extends EntityRepository
{

    public function save(TruckArrivalLine $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TruckArrivalLine $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return TruckArrivalLine[] Returns an array of TruckArrivalLine objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TruckArrivalLine
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
