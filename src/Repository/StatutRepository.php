<?php

namespace App\Repository;

use App\Entity\Statut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method Statut|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statut|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statut[]    findAll()
 * @method Statut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatutRepository extends ServiceEntityRepository
{

	/**
	 * @param string $categorieName
	 * @param bool $ordered
	 * @return Statut[]
	 */
    public function findByCategorieName($categorieName, $ordered = false)
    {
        $em = $this->getEntityManager();

        $dql = "SELECT s
            FROM App\Entity\Statut s
            JOIN s.categorie c
            WHERE c.nom = :categorieName";

        if ($ordered) {
        	$dql .= " ORDER BY s.displayOrder ASC";
		}

		$query = $em->createQuery($dql);

        $query->setParameter("categorieName", $categorieName);

        return $query->execute();
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
     * @param string $statutName
     * @return Statut | null
     * @throws NonUniqueResultException
     */
    public function findOneByCategorieNameAndStatutName($categorieName, $statutName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s
          FROM App\Entity\Statut s
          JOIN s.categorie c
          WHERE c.nom = :categorieName AND s.nom = :statutName
          "
        );

        $query->setParameters([
            'categorieName' => $categorieName,
            'statutName' => $statutName
        ]);

        return $query->getOneOrNullResult();
    }

	/**
	 * @param string $label
	 * @param string $category
	 * @return int
	 * @throws NonUniqueResultException
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
