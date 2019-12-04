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
	 */
    public function findByDemandeCollecte($collecte)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            'SELECT oc
            FROM App\Entity\OrdreCollecte oc
            WHERE oc.demandeCollecte = :collecte'
        )->setParameter('collecte', $collecte);
        return $query->execute();
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

	/**
	 * @param string $statutLabel
	 * @param Utilisateur $user
	 * @return mixed
	 */
	public function getByStatutLabelAndUser($statutLabel, $user)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager
            ->createQuery($this->getQueryBy() . " WHERE (s.nom = :statutLabel AND (oc.utilisateur IS NULL OR oc.utilisateur = :user))")
            ->setParameters([
                'statutLabel' => $statutLabel,
                'user' => $user,
            ]);
		return $query->execute();
	}

	/**
	 * @param int $ordreCollecteId
	 * @return mixed
	 */
	public function getById($ordreCollecteId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager
            ->createQuery($this->getQueryBy() . " WHERE oc.id = :id")
            ->setParameter('id', $ordreCollecteId);
		$result = $query->execute();
		return !empty($result) ? $result[0] : null;
	}

	private function getQueryBy(): string  {
	    return (/** @lang DQL */
            "SELECT oc.id,
                    oc.numero as number,
                    pc.label as location_from,
                    dc.stockOrDestruct as forStock
            FROM App\Entity\OrdreCollecte oc
            LEFT JOIN oc.demandeCollecte dc
            LEFT JOIN dc.pointCollecte pc
            LEFT JOIN oc.statut s"
        );
    }
}
