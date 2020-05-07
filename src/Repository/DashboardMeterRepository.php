<?php

namespace App\Repository;

use App\Entity\DashboardMeter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method DashboardMeter|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardMeter|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardMeter[]    findAll()
 * @method DashboardMeter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardMeterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardMeter::class);
    }

    // /**
    //  * @return DashboardMeter[] Returns an array of DashboardMeter objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DashboardMeter
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function clearTable(): void {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "DELETE
            FROM App\Entity\DashboardMeter d
           "
        );
        $query->execute();
    }

    /**
     * @param string $key
     * @param string $dashboard
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getByKeyAndDashboard(string $key, string $dashboard): array {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "
                SELECT d.label, d.count, d.delay
                FROM App\Entity\DashboardMeter d
                WHERE d.meterKey = :key AND d.dashboard = :dashboard
           "
        )->setParameters([
            'key' => $key,
            'dashboard' => $dashboard,
        ]);
        return $query->getSingleResult();
    }
}
