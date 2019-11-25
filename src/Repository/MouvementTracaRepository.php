<?php

namespace App\Repository;

use App\Entity\MouvementTraca;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method MouvementTraca|null find($id, $lockMode = null, $lockVersion = null)
 * @method MouvementTraca|null findOneBy(array $criteria, array $orderBy = null)
 * @method MouvementTraca[]    findAll()
 * @method MouvementTraca[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MouvementTracaRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MouvementTraca::class);
    }

    public function findOneByUniqueIdForMobile($uniqueId) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
			'SELECT mvt
                FROM App\Entity\MouvementTraca mvt
                WHERE mvt.uniqueIdForMobile = :date'
        )->setParameter('date', $uniqueId);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param $dateMin
	 * @param $dateMax
	 * @return MouvementTraca[]
	 * @throws \Exception
	 */
    public function findByDates($dateMin, $dateMax)
    {
        $dateMinDate = new \DateTime($dateMin);
        $dateMaxDate = new \DateTime($dateMax);
        $dateMaxDate->modify('+1 day');
        $dateMinDate->modify('-1 day');
        $dateMax = $dateMaxDate->format('Y-m-d H:i:s');
        $dateMin = $dateMinDate->format('Y-m-d H:i:s');
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            'SELECT m
            FROM App\Entity\MouvementTraca m
            WHERE m.datetime BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

	/**
	 * @param string $colis
	 * @return  MouvementTraca
	 */
    public function getLastByColis($colis)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT mt
			FROM App\Entity\MouvementTraca mt
			WHERE mt.colis = :colis
			ORDER BY mt.datetime DESC"
		)->setParameter('colis', $colis);

		$result = $query->execute();
		return $result ? $result[0] : null;
	}
}
