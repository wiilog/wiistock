<?php

namespace App\Repository;

use App\Entity\Statut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Statut|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statut|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statut[]    findAll()
 * @method Statut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Statut::class);
    }

    /**
     * @param string $categorieName
     * @param bool $ordered
     * @return Statut[]
     */
    public function findByCategorieName($categorieName, $ordered = false)
    {
        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom = :categorieName');

        if ($ordered) {
            $queryBuilder->orderBy('status.displayOrder', 'ASC');
		}

        $queryBuilder
            ->setParameter("categorieName", $categorieName);

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    /**
     * @param array $categorieNames
     * @param bool $ordered
     * @return Statut[]
     */
    public function findByCategorieNames($categorieNames, $ordered = false)
    {
        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom IN (:categorieNames)')
            ->setParameter("categorieNames", $categorieNames, Connection::PARAM_STR_ARRAY);

        if ($ordered) {
            $queryBuilder->orderBy('status.displayOrder', 'ASC');
        }

        return $queryBuilder->getQuery()->execute();
    }

    public function findByName($name)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s
            FROM App\Entity\Statuts s
            WHERE s.nom = :name"
        );
        $query->setParameter("name", $name);

        return $query->execute();
    }


    /**
     * @param string $categorieName
     * @param string $statutCode
     * @return Statut | null
     * @throws NonUniqueResultException
     */
    public function findOneByCategorieNameAndStatutCode($categorieName, $statutCode)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s
          FROM App\Entity\Statut s
          JOIN s.categorie c
          WHERE c.nom = :categorieName AND s.code = :statutCode
          "
        );

        $query->setParameters([
            'categorieName' => $categorieName,
            'statutCode' => $statutCode
        ]);

        return $query->getOneOrNullResult();
    }

    /**
     * @param string $categorieName
     * @param string[] $listStatusName
     * @return Statut[]
     */
    public function getIdByCategorieNameAndStatusesNames($categorieName, $listStatusName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s.id
			  FROM App\Entity\Statut s
			  JOIN s.categorie c
			  WHERE c.nom = :categorieName AND s.nom IN (:listStatusName)
          "
        );

        $query
			->setParameter('categorieName', $categorieName)
			->setParameter('listStatusName', $listStatusName, Connection::PARAM_STR_ARRAY);

		return array_column($query->execute(), 'id');
    }

	/**
	 * @param string $categorieName
	 * @param string $statusName
	 * @return int
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function getOneIdByCategorieNameAndStatusName($categorieName, $statusName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s.id
			  FROM App\Entity\Statut s
			  JOIN s.categorie c
			  WHERE c.nom = :categorieName AND s.nom = :statusName
          "
        );

        $query
			->setParameters([
				'categorieName' => $categorieName,
				'statusName' => $statusName
			]);

		return $query->getSingleScalarResult();
    }

	/**
	 * @param string $label
	 * @param string $category
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function countByLabelAndCategory($label, $category)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(s)
            FROM App\Entity\Statut s
            WHERE LOWER(s.nom) = :label AND s.categorie = :category
           "
		)->setParameters([
			'label' => $label,
			'category' => $category
		]);

		return $query->getSingleScalarResult();
	}

	public function countByLabelDiff($label, $statusLabel, $category)
	{
		$em = $this->getEntityManager();

		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT count(s)
            FROM App\Entity\Statut s
            WHERE s.nom = :label AND s.nom != :statusLabel AND s.categorie = :category"
		)->setParameters([
			'label' => $label,
			'statusLabel' => $statusLabel,
			'category' => $category
		]);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param int $id
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function countUsedById($id)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/** @lang DQL */
			"SELECT COUNT(s)
            FROM App\Entity\Statut s
            LEFT JOIN s.articles a
            LEFT JOIN s.collectes c
            LEFT JOIN s.demandes dl
            LEFT JOIN s.livraisons ol
            LEFT JOIN s.preparations p
            LEFT JOIN s.litiges l
            LEFT JOIN s.receptions r
            LEFT JOIN s.referenceArticles ra
            LEFT JOIN s.manutentions m
            WHERE a.statut = :id OR c.statut = :id OR dl.statut = :id OR ol.statut = :id OR p.statut = :id
            OR l.status = :id OR r.statut = :id OR ra.statut = :id OR m.statut = :id
           "
		)->setParameter('id', $id);

		return $query->getSingleScalarResult();
	}
}
