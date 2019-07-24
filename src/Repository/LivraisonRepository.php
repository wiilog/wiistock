<?php

namespace App\Repository;

use App\Entity\Livraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Livraison|null find($id, $lockMode = null, $lockVersion = null)
 * @method Livraison|null findOneBy(array $criteria, array $orderBy = null)
 * @method Livraison[]    findAll()
 * @method Livraison[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LivraisonRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Livraison::class);
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(l)
            FROM App\Entity\Livraison l
            JOIN l.destination dest
            WHERE dest.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    public function findOneByPreparationId($preparationId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT l
			FROM App\Entity\Demande d 
			JOIN d.livraison l
			JOIN d.preparation p
			WHERE p.id = :preparationId
			"
		)->setParameter('preparationId', $preparationId);

		return $query->getOneOrNullResult();
	}
}
