<?php

namespace App\Repository;

use App\Entity\AlerteExpiry;
use App\Entity\Article;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method AlerteExpiry|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlerteExpiry|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlerteExpiry[]    findAll()
 * @method AlerteExpiry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlerteExpiryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, AlerteExpiry::class);
    }

	public function countActivatedDateReached()
	{
		$entityManager = $this->getEntityManager();

		$now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
		$now->setTime(0,0);

		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(a)
			FROM App\Entity\AlerteExpiry a
			LEFT JOIN a.refArticle ra
			WHERE DATE_ADD(:now, a.nbPeriod, a.typePeriod) < ra.expiryDate" //TODO CG pb ici
		)
		->setParameter('now', $now);

		return $query->getSingleScalarResult();
	}

}
