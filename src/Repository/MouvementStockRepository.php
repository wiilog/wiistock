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
            "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m
            JOIN m.emplacement empl
            WHERE empl.id = :emplacementId"
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
}
