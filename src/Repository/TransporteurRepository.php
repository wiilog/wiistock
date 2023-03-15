<?php

namespace App\Repository;

use App\Entity\Transporteur;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @method Transporteur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transporteur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transporteur[]    findAll()
 * @method Transporteur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransporteurRepository extends EntityRepository
{
    public function findAllSorted() {
        return $this->createQueryBuilder("t")
            ->orderBy("t.label")
            ->getQuery()
            ->getResult();
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
        $now = new DateTime('now');
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
     * @param Transporteur|null $current
	 * @return int
	 */
    public function countByCode(string $code, Transporteur $current = null): int
    {
        $qb = $this->createQueryBuilder('carrier')
            ->select("COUNT(carrier)")
            ->andWhere('carrier.code = :code')
            ->setParameter('code', $code);

        if ($current) {
            $qb
                ->andWhere('carrier != :current')
                ->setParameter('current', $current);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

	/**
	 * @param string $label
     * @param Transporteur|null $current
	 * @return int
	 */
    public function countByLabel(string $label, Transporteur $current = null): int
    {
        $qb = $this->createQueryBuilder('carrier')
            ->select("COUNT(carrier)")
            ->andWhere('carrier.label = :label')
            ->setParameter('label', $label);

        if ($current) {
            $qb
                ->andWhere('carrier != :current')
                ->setParameter('current', $current);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
	}

    public function getForSelect(?string $term) {
        return $this->createQueryBuilder("carrier")
            ->select("carrier.id AS id, carrier.label AS text")
            ->where("carrier.label LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }
}
