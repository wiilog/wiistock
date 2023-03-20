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

    public function getForSelect(?string $term, $option = []): array {
        $qb = $this
            ->createQueryBuilder('truck_arrival_line')
            ->select("truck_arrival_line.id AS id")
            ->addSelect("truck_arrival_line.number AS text")
            ->addSelect("truck_arrival.number AS truck_arrival_number")
            ->addSelect("truck_arrival.id AS truck_arrival_id")
            ->addSelect("driver.id AS driver_id")
            ->addSelect("driver.prenom AS driver_first_name")
            ->addSelect("driver.nom AS driver_last_name")
            ->andWhere("truck_arrival_line.number LIKE :term")
            ->join('truck_arrival_line.truckArrival', 'truck_arrival')
            ->join('truck_arrival.driver', 'driver')
            ->setParameter('term', "%$term%");


        if (isset($option['truckArrivalId'])) {
            $qb
                ->andWhere('truck_arrival.id = :truck_arrival_id')
                ->setParameter('truck_arrival_id', $option['truckArrivalId']);
        }

        if (isset($option['carrierId'])) {
            $qb
                ->andWhere('truck_arrival.carrier = :carrier_id')
                ->setParameter('carrier_id', $option['carrierId']);
        }

        return $qb->getQuery()->getArrayResult();
    }
}
