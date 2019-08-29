<?php

namespace App\Repository;

use App\Entity\Alerte;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Alerte|null find($id, $lockMode = null, $lockVersion = null)
 * @method Alerte|null findOneBy(array $criteria, array $orderBy = null)
 * @method Alerte[]    findAll()
 * @method Alerte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlerteRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Alerte::class);
    }

    public function countActivatedLimitReached()
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(a)
			FROM App\Entity\Alerte a
			JOIN a.refArticle ra
			WHERE ra.quantiteStock <= a.limitAlert OR ra.quantiteStock <= a.limitSecurity
			AND a.activated = 1"
		);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param ReferenceArticle $refArticle
	 * @return int
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function countByRef($refArticle)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT count(a) 
			FROM App\Entity\Alerte a
			WHERE a.refArticle = :refArticle"
		)->setParameter('refArticle', $refArticle);

		return $query->getSingleScalarResult();
	}
}
