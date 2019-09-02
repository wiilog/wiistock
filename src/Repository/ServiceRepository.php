<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Service|null find($id, $lockMode = null, $lockVersion = null)
 * @method Service|null findOneBy(array $criteria, array $orderBy = null)
 * @method Service[]    findAll()
 * @method Service[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findByUser($user){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT u
            FROM App\Entity\Service u
            WHERE u.demandeur = $user
           "
            );
        return $query->execute(); 
    }

    public function countByStatut($statut){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(u)
            FROM App\Entity\Service u
            WHERE u.statut = :statut 
           "
            )->setParameter('statut', $statut);
        return $query->getSingleScalarResult(); 
    }

	/**
	 * @param Utilisateur $user
	 * @return int
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function countByUser($user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(s)
            FROM App\Entity\Service s
            WHERE s.demandeur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}
}
