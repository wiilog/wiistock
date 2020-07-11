<?php

namespace App\Repository;

use App\Entity\DaysWorked;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method DaysWorked|null find($id, $lockMode = null, $lockVersion = null)
 * @method DaysWorked|null findOneBy(array $criteria, array $orderBy = null)
 * @method DaysWorked[]    findAll()
 * @method DaysWorked[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DaysWorkedRepository extends EntityRepository
{
	/**
	 * @return DaysWorked[]
	 */
    public function findAllOrdered()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT dp
            FROM App\Entity\DaysWorked dp
            ORDER BY dp.displayOrder ASC
            "
        );
        return $query->execute();
    }

	/**
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
    public function countEmptyTimes()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(dw)
            FROM App\Entity\DaysWorked dw
            WHERE dw.times IS NULL AND dw.worked = 1
            ");
        return $query->getSingleScalarResult();
    }

	/**
	 * @return string[]
	 */
    public function getLabelWorkedDays()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT dw.day
            FROM App\Entity\DaysWorked dw
            WHERE dw.times IS NOT NULL AND dw.worked = 1
            ");
		return array_column($query->execute(), 'day');

	}

	/**
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function countDaysWorked()
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/** @lang DQL */
			"SELECT COUNT(dw)
            FROM App\Entity\DaysWorked dw
            WHERE dw.times IS NOT NULL AND dw.worked = 1
            ");
		return $query->getSingleScalarResult();
	}

	public function getWorkedTimeForEachDaysWorked() {
        return array_reduce(
            $this->findAllOrdered(),
            function (array $carry, DaysWorked $daysWorked) {
                $times = $daysWorked->getTimes();
                $worked = $daysWorked->getWorked();
                if ($worked && !empty($times)) {
                    $carry[strtolower($daysWorked->getDay())] = $daysWorked->getTimes();
                }
                return $carry;
            },
            []);
    }

}
