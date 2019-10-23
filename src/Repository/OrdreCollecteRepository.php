<?php

namespace App\Repository;

use App\Entity\Collecte;
use App\Entity\OrdreCollecte;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method OrdreCollecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdreCollecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdreCollecte[]    findAll()
 * @method OrdreCollecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdreCollecteRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, OrdreCollecte::class);
    }

	/**
	 * @param Collecte $collecte
	 * @return OrdreCollecte
	 * @throws NonUniqueResultException
	 */
    public function findOneByDemandeCollecte($collecte)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT oc
            FROM App\Entity\OrdreCollecte oc
            WHERE oc.demandeCollecte = :collecte'
        )->setParameter('collecte', $collecte);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param Utilisateur $user
	 * @return int
	 * @throws NonUniqueResultException
	 */
	public function countByUser($user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(o)
            FROM App\Entity\OrdreCollecte o
            WHERE o.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}


}

	/**
	 * @param string $statutLabel
	 * @param Utilisateur $user
	 * @param int[] $userTypes
	 * @return mixed
	 */
	public function getByStatutLabelAndUser($statutLabel, $user, $userTypes)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/** @lang DQL */
			"SELECT oc.id, oc.numero as number, pc.label as location, dc.stockOrDestruct as forStock
            FROM App\Entity\OrdreCollecte oc
            LEFT JOIN oc.demandeCollecte dc
            LEFT JOIN dc.pointCollecte pc
            LEFT JOIN oc.statut s
            LEFT JOIN dc.type t
            WHERE (s.nom = :statutLabel AND (oc.utilisateur IS NULL OR oc.utilisateur = :user))
            AND t.id in (:type)"
		)->setParameters([
			'statutLabel' => $statutLabel,
			'user' => $user,
			'type' => $userTypes
		]);
		return $query->execute();
	}
}