<?php

namespace App\Repository;

use App\Entity\Transporteur;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @method Transporteur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transporteur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transporteur[]    findAll()
 * @method Transporteur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransporteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transporteur::class);
    }

    public function findAllSorted() {
        return $this->createQueryBuilder("t")
            ->orderBy("t.label")
            ->getQuery()
            ->getResult();
    }

    public function findByIds(array $ids): array {
        if (!empty($ids)) {
            return $this->createQueryBuilder('carrier')
                ->where('carrier.id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
                ->getQuery()
                ->getResult();
        }
        else {
            return [];
        }
    }

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT e.id, e.label as text
          FROM App\Entity\Transporteur e
          WHERE e.label LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    /**
     * @param array $filterIds
     * @return array
     * @throws Exception
     */
    public function getDailyArrivalCarriersLabel(array $filterIds = []) {
        $now = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $beginDayDate = clone $now;
        $beginDayDate->setTime(0, 0, 0);
        $endDayDate = clone $now;
        $endDayDate->setTime(23, 59, 59);
        $queryBuilder = $this->createQueryBuilder('carrier')
            ->join('carrier.arrivages', 'arrival')
            ->where('arrival.date BETWEEN :dateMin AND :dateMax')
            ->setParameter('dateMin', $beginDayDate)
            ->setParameter('dateMax', $endDayDate)
            ->select('carrier.id AS id')
            ->addSelect('carrier.label AS label')
            ->addSelect('COUNT(arrival.id) AS arrivalsCounter')
            ->groupBy('carrier.id')
            ->having('arrivalsCounter > 0')
            ->orderBy('arrivalsCounter', 'DESC')
            ->addOrderBy('carrier.id', 'DESC');

        if (!empty($filterIds)) {
            $queryBuilder
                ->andWhere('carrier.id IN (:filterCarrierIds)')
                ->setParameter('filterCarrierIds', $filterIds, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder->getQuery()->getArrayResult();
    }

    public function findOneByCode($code)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT t
          FROM App\Entity\Transporteur t
          WHERE t.code = :search"
        )->setParameter('search', $code);

        return $query->getOneOrNullResult();
    }

	/**
	 * @param string $code
	 * @return int
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function countByCode($code)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT COUNT(t)
          FROM App\Entity\Transporteur t
          WHERE t.code = :code"
		)->setParameter('code', $code);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param string $label
	 * @return int
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function countByLabel($label)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT COUNT(t)
          FROM App\Entity\Transporteur t
          WHERE t.label = :label"
		)->setParameter('label', $label);

		return $query->getSingleScalarResult();
	}

    /**
     * @param array $ids
     * @return array
     */
    public function findByIds(array $ids): array {
        return $this->createQueryBuilder('carrier')
            ->where('carrier.id IN (:ids)')
            ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->getResult();
    }
}
