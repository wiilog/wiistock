<?php

namespace App\Repository;

use App\Entity\TruckArrivalLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
    }

    public function iterateAll(){
        return $this->createQueryBuilder('truck_arrival_line')
            ->select('truck_arrival_line.number')
            ->getQuery()
            ->getArrayResult();
    }
}
