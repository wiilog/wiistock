<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ReceptionReferenceArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionReferenceArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionReferenceArticle[]    findAll()
 * @method ReceptionReferenceArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionReferenceArticleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ReceptionReferenceArticle::class);
    }

	/**
	 * @param Reception $reception
	 * @return ReceptionReferenceArticle[]|null
	 */
    public function findByReception($reception)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\ReceptionReferenceArticle a
            WHERE a.reception = :reception'
        )->setParameter('reception', $reception);;
        return $query->execute();
    }

    public function countNotConformByReception($reception)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT (a)
            FROM App\Entity\ReceptionReferenceArticle a
            WHERE a.anomalie = :conform AND a.reception = :reception"
        )->setParameters([
            'conform' => 1,
            'reception' => $reception
        ]);
        return $query->getSingleScalarResult();;
    }

	public function countByFournisseurId($fournisseurId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT COUNT(rra)
			FROM App\Entity\ReceptionReferenceArticle rra
			WHERE rra.fournisseur = :fournisseurId"
		)->setParameter('fournisseurId', $fournisseurId);

		return $query->getSingleScalarResult();
	}

	public function countByReceptionId($receptionId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT COUNT(rra)
            FROM App\Entity\ReceptionReferenceArticle rra
            WHERE rra.reception = :receptionId'
		)->setParameter('receptionId', $receptionId);;
		return $query->getSingleScalarResult();
	}

	/**
	 * @param Reception $reception
	 * @param string $noCommande
	 * @param ReferenceArticle $refArticle
	 * @return ReceptionReferenceArticle|null
	 * @throws NonUniqueResultException
	 */
	public function findOneByReceptionAndCommandeAndRefArticle($reception, $noCommande, $refArticle)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/** @lang DQL */
			'SELECT rra
            FROM App\Entity\ReceptionReferenceArticle rra
            WHERE rra.reception = :reception
            AND rra.referenceArticle = :refArticle
            AND rra.commande = :noCommande
            '
		)->setParameters([
			'reception' => $reception,
			'noCommande' => $noCommande,
			'refArticle' => $refArticle
		]);
		return $query->getOneOrNullResult();
	}

}
