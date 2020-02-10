<?php

namespace App\Repository;

use App\Entity\Colis;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Colis|null find($id, $lockMode = null, $lockVersion = null)
 * @method Colis|null findOneBy(array $criteria, array $orderBy = null)
 * @method Colis[]    findAll()
 * @method Colis[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ColisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Colis::class);
    }

	/**
	 * @param string $code
	 * @return Colis|null
	 * @throws NonUniqueResultException
	 */
    public function findOneByCode($code)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery('
            SELECT c
            FROM App\Entity\Colis c
            WHERE c.code = :code'
        )->setParameter('code', $code);
        return $query->getOneOrNullResult();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countByDates(DateTime $dateMin, DateTime $dateMax): int
    {
        $dateMinStr = $dateMin->format('Y-m-d H:i:s');
        $dateMaxStr = $dateMax->format('Y-m-d H:i:s');

        return $this->createQueryBuilder('colis')
            ->join('colis.arrivage', 'arrivage')
            ->where('arrivage.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMinStr,
                'dateMax' => $dateMaxStr
            ])
            ->distinct()
            ->select('count(colis)')
            ->getQuery()
            ->getSingleScalarResult();
    }

}
