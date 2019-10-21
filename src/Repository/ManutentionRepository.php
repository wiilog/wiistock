<?php

namespace App\Repository;

use App\Entity\Manutention;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Manutention|null find($id, $lockMode = null, $lockVersion = null)
 * @method Manutention|null findOneBy(array $criteria, array $orderBy = null)
 * @method Manutention[]    findAll()
 * @method Manutention[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ManutentionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Manutention::class);
    }

    public function countByStatut($statut){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\Manutention m
            WHERE m.statut = :statut 
           "
            )->setParameter('statut', $statut);
        return $query->getSingleScalarResult(); 
    }

    public function findByStatut($statut) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @Lang DQL */
        "SELECT m.id, m.date_attendue, m.demandeur, m.commentaire, m.source, m.destination
        FROM App\Entity\Manutention m
        WHERE m.statut = :statut
        "
        )->setParameter('statut', $statut);
        return $query->execute();
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
			"SELECT COUNT(m)
            FROM App\Entity\Manutention m
            WHERE m.demandeur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}
}
