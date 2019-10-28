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

    public function getByStatusLabelAndUser($statusLabel, $statutEnCoursLabel, $user, $userTypes)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p.id, 
                         p.numero as number,
                         dest.label as destination
			FROM App\Entity\Preparation p
			JOIN p.statut s
			JOIN p.demandes d
			JOIN d.destination dest
			JOIN d.type t
			WHERE (s.nom = :statusLabel or (s.nom = :enCours AND p.utilisateur = :user)) AND t.id IN (:type)"
        )->setParameters([
            'statusLabel' => $statusLabel,
            'user' => $user,
            'enCours' => $statutEnCoursLabel,
            'type' => $userTypes,
        ]);
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
			"SELECT COUNT(p)
            FROM App\Entity\Preparation p
            WHERE p.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

}
