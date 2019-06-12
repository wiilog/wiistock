<?php

namespace App\Repository;

use App\Entity\Litige;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Litige|null find($id, $lockMode = null, $lockVersion = null)
 * @method Litige|null findOneBy(array $criteria, array $orderBy = null)
 * @method Litige[]    findAll()
 * @method Litige[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LitigeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Litige::class);
    }

    public function findByArrivageStatutLabel($statutLabel)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT l
			FROM App\Entity\Litige
			JOIN l.arrivage a
			JOIN a.statut s
			WHERE s.nom = :statutLabel"
		)->setParameter('statutLabel', $statutLabel);

		$query->execute();
	}

}
