<?php

namespace App\Repository;

use App\Entity\CountSimultaneousOpenedSessions;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CountSimultaneousOpenedSessions>
 *
 * @method CountSimultaneousOpenedSessions|null find($id, $lockMode = null, $lockVersion = null)
 * @method CountSimultaneousOpenedSessions|null findOneBy(array $criteria, array $orderBy = null)
 * @method CountSimultaneousOpenedSessions[]    findAll()
 * @method CountSimultaneousOpenedSessions[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CountSimultaneousOpenedSessionsRepository extends EntityRepository
{
    public function getByDates(DateTime $start, DateTime $end): array {
        $queryBuilder = $this->createQueryBuilder("countSimultaneousOpenedSessions");

        $queryBuilder
            ->select("countSimultaneousOpenedSessions")
            ->where("countSimultaneousOpenedSessions.dateTime >= :start")
            ->andWhere("countSimultaneousOpenedSessions.dateTime <= :end")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->orderBy("countSimultaneousOpenedSessions.dateTime", "ASC");

        return $queryBuilder->getQuery()->getResult();
    }
}
