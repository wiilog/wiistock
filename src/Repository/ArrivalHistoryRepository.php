<?php

namespace App\Repository;

use App\Entity\ArrivalHistory;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method ArrivalHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArrivalHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArrivalHistory[]    findAll()
 * @method ArrivalHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArrivalHistoryRepository extends EntityRepository
{

    /**
     * @param $date
     * @return ArrivalHistory | null
     * @throws NonUniqueResultException
     */
    public function getByDate($date)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
        "SELECT h
              FROM App\Entity\ArrivalHistory h
              WHERE h.day = :date"
        )->setParameter('date', $date);
        return $query->getOneOrNullResult();
    }
}
