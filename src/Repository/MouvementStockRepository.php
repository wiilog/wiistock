<?php

namespace App\Repository;

use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method MouvementStock|null find($id, $lockMode = null, $lockVersion = null)
 * @method MouvementStock|null findOneBy(array $criteria, array $orderBy = null)
 * @method MouvementStock[]    findAll()
 * @method MouvementStock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MouvementStockRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MouvementStock::class);
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m
            JOIN m.emplacementFrom ef
            JOIN m.emplacementTo et
            WHERE ef.id = :emplacementId OR et.id =:emplacementId"
        )->setParameter('emplacementId', $emplacementId);
        return $query->getSingleScalarResult();
    }

	/**
	 * @param Preparation $preparation
	 * @return MouvementStock[]
	 */
    public function findByPreparation($preparation)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.preparationOrder = :preparation"
		)->setParameter('preparation', $preparation);

		return $query->execute();
	}

	/**
	 * @param Livraison $livraison
	 * @return MouvementStock[]
	 */
	public function findByLivraison($livraison)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.livraisonOrder = :livraison"
		)->setParameter('livraison', $livraison);

		return $query->execute();
	}

	/**
	 * @param $dateMin
	 * @param $dateMax
	 * @return MouvementStock[]
	 * @throws \Exception
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$dateMinDate = new \DateTime($dateMin);
		$dateMaxDate = new \DateTime($dateMax);
		$dateMaxDate->modify('+1 day');
		$dateMinDate->modify('-1 day');
		$dateMax = $dateMaxDate->format('Y-m-d H:i:s');
		$dateMin = $dateMinDate->format('Y-m-d H:i:s');
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.date BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}

    /**
     * @param string $types
     * @return mixed
     */
	public function countByTypes($types)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
        "SELECT COUNT(m) 
            FROM App\Entity\MouvementStock m 
            WHERE m.type 
            IN (:types)"
        )->setParameter('types', $types);
        return $query->getSingleScalarResult();
    }

    /**
     * @param $type
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAllMouvementStock()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "SELECT COUNT(m) FROM App\Entity\MouvementStock m"
        );
        return $query->getSingleScalarResult();
    }

    public function countTotalPriceRefArticle()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "SELECT SUM(m.quantity * ra.prixUnitaire) FROM App\Entity\MouvementStock m JOIN m.refArticle ra WHERE ra.typeQuantite = 'reference'"
            );
        return $query->getSingleScalarResult();
    }

    public function countTotalPriceArticle()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "SELECT SUM(m.quantity * a.prixUnitaire) FROM App\Entity\MouvementStock m JOIN m.article a"
        );
        return $query->getSingleScalarResult();
    }
}
