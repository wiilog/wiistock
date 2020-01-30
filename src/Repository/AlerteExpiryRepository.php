<?php

namespace App\Repository;

use App\Entity\AlerteExpiry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AlerteExpiry|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlerteExpiry|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlerteExpiry[]    findAll()
 * @method AlerteExpiry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlerteExpiryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlerteExpiry::class);
    }

	public function countAlertsExpiryActive()
	{
		$entityManager = $this->getEntityManager();

		$now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
		$now->setTime(0,0);
		$now = $now->format('Y-m-d H:i:s');

		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(DISTINCT(ra))
			FROM App\Entity\AlerteExpiry a
			LEFT JOIN a.refArticle ra
			WHERE (
				(a.typePeriod = :jour AND DATE_SUB(ra.expiryDate, a.nbPeriod, 'day') <= '" . $now . "')
				OR
				(a.typePeriod = :semaine AND DATE_SUB(ra.expiryDate, a.nbPeriod, 'week') <= '" . $now . "')
				OR
				(a.typePeriod = :mois AND DATE_SUB(ra.expiryDate, a.nbPeriod, 'month') <= '" . $now . "')
			)"
		)->setParameters([
			'jour' => AlerteExpiry::TYPE_PERIOD_DAY,
			'semaine' => AlerteExpiry::TYPE_PERIOD_WEEK,
			'mois' => AlerteExpiry::TYPE_PERIOD_MONTH
		]);

		return $query->getSingleScalarResult();
	}

	public function countAlertsExpiryGeneralActive()
	{
		$entityManager = $this->getEntityManager();

		$now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
		$now->setTime(0,0);
		$now = $now->format('Y-m-d H:i:s');

		$query = $entityManager->createQuery(
		/** @lang DQL */
			"SELECT COUNT(DISTINCT(ra))
			FROM App\Entity\AlerteExpiry a, App\Entity\ReferenceArticle ra
			WHERE
				a.refArticle IS NULL
				AND (
					(a.typePeriod = :jour AND DATE_SUB(ra.expiryDate, a.nbPeriod, 'day') <= '" . $now . "')
					OR
					(a.typePeriod = :semaine AND DATE_SUB(ra.expiryDate, a.nbPeriod, 'week') <= '" . $now . "')
					OR
					(a.typePeriod = :mois AND DATE_SUB(ra.expiryDate, a.nbPeriod, 'month') <= '" . $now . "')
				)"
		)->setParameters([
			'jour' => AlerteExpiry::TYPE_PERIOD_DAY,
			'semaine' => AlerteExpiry::TYPE_PERIOD_WEEK,
			'mois' => AlerteExpiry::TYPE_PERIOD_MONTH
		]);

		return $query->getSingleScalarResult();
	}


}
