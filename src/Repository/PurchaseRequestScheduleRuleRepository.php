<?php

namespace App\Repository;

use App\Entity\PurchaseRequestScheduleRule;
use App\Entity\Zone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseRequestScheduleRule>
 *
 * @method PurchaseRequestScheduleRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseRequestScheduleRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseRequestScheduleRule[]    findAll()
 * @method PurchaseRequestScheduleRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseRequestScheduleRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseRequestScheduleRule::class);
    }

    public function save(PurchaseRequestScheduleRule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PurchaseRequestScheduleRule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function isZoneInPurchaseRequestScheduleRule(Zone $zone): bool {
        return $this->createQueryBuilder('purchaseRequestScheduleRule')
            ->select('COUNT(purchaseRequestScheduleRule.id)')
            ->where('zone.id = :zoneId')
            ->join('purchaseRequestScheduleRule.zones', 'zone')
            ->setParameter('zoneId', $zone->getId())
            ->getQuery()
            ->getSingleScalarResult() > 0 ;
    }

//    /**
//     * @return PurchaseRequestScheduleRule[] Returns an array of PurchaseRequestScheduleRule objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PurchaseRequestScheduleRule
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
