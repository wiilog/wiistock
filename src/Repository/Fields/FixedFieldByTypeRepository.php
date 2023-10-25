<?php

namespace App\Repository\Fields;

use App\Entity\Fields\FixedFieldByType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FixedFieldByType>
 *
 * @method FixedFieldByType|null find($id, $lockMode = null, $lockVersion = null)
 * @method FixedFieldByType|null findOneBy(array $criteria, array $orderBy = null)
 * @method FixedFieldByType[]    findAll()
 * @method FixedFieldByType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FixedFieldByTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FixedFieldByType::class);
    }

//    /**
//     * @return FixedFieldByType[] Returns an array of FixedFieldByType objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('f.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?FixedFieldByType
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
