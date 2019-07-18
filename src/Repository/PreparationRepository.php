<?php

namespace App\Repository;

use App\Entity\Preparation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Preparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Preparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Preparation[]    findAll()
 * @method Preparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Preparation::class);
    }

	public function getByDemande($id)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT p
           FROM App\Entity\Preparation p
           JOIN p.demande d
           WHERE d.id = :id "
		)->setParameter('id', $id);

		return $query->execute();
	}


	public function getByStatusLabel($statusLabel)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT p.id, p.number
			FROM App\Entity\Preparation p
			JOIN p.statut s
			WHERE s.nom = :statusLabel"
		)->setParameter('statusLabel', $statusLabel);

		return $query->execute();
	}
}
