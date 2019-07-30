<?php

namespace App\Repository;

use App\Entity\Reception;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Reception|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reception|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reception[]    findAll()
 * @method Reception[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Reception::class);
    }

	public function countByFournisseur($fournisseurId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT COUNT(r)
			FROM App\Entity\Reception r
			WHERE r.fournisseur = :fournisseurId"
		)->setParameter('fournisseurId', $fournisseurId);

		return $query->getSingleScalarResult();
	}

//    public function findForIndex()
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT r.id, r.date, r.numeroReception, r.dateAttendu, s.nom as statut, f.nom as fournisseur
//            FROM App\Entity\Receptions r
//            JOIN r.statut s JOIN r.fournisseur f
//           "
//        );;
//        return $query->execute();
//    }

}