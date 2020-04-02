<?php

namespace App\Repository;

use App\Entity\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method Type|null find($id, $lockMode = null, $lockVersion = null)
 * @method Type|null findOneBy(array $criteria, array $orderBy = null)
 * @method Type[]    findAll()
 * @method Type[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TypeRepository extends EntityRepository
{
	/**
	 * @param string $categoryLabel
	 * @param string|null $order ("asc" ou "desc")
	 * @return Type[]
	 */
    public function findByCategoryLabel($categoryLabel, $order = null)
    {
        $em = $this->getEntityManager();
        $dql = "SELECT t
            FROM App\Entity\Type t
            JOIN t.category c
            WHERE c.label = :category";

        if ($order) {
        	$dql .= " ORDER BY t.label " . $order;
		}
        $query = $em->createQuery($dql);

        $query->setParameter("category", $categoryLabel);

        return $query->execute();
    }

    public function getIdAndLabelByCategoryLabel($category)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT t.id, t.label
            FROM App\Entity\Type t
            JOIN t.category c
            WHERE c.label = :category"
        );
        $query->setParameter("category", $category);

        return $query->execute();
    }

    public function getOneIdAndLabelByCategoryLabel($category)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT t.id, t.label
            FROM App\Entity\Type t
            JOIN t.category c
            WHERE c.label = :category"
        );
        $query->setParameter("category", $category);
        $result = $query->execute();

        return $result ? $result[0] : null;
    }

    public function findOneByCategoryLabel($category)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT t
            FROM App\Entity\Type t
            JOIN t.category c
            WHERE c.label = :category"
        );
        $query->setParameter("category", $category);
        $result = $query->execute();

        return $result ? $result[0] : null;
    }

    public function countByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(t)
            FROM App\Entity\Type t
            WHERE LOWER(t.label) = :label
           "
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

    public function countByLabelAndCategory($label, $category)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(t)
            FROM App\Entity\Type t
            WHERE LOWER(t.label) = :label AND t.category = :category
           "
        )->setParameters([
            'label' => $label,
            'category' => $category
        ]);

        return $query->getSingleScalarResult();
    }

    public function countUsedById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(t)
            FROM App\Entity\Type t
            LEFT JOIN t.articles a
            LEFT JOIN t.champsLibres cl
            LEFT JOIN t.collectes c
            LEFT JOIN t.demandesLivraison dl
            LEFT JOIN t.litiges l
            LEFT JOIN t.receptions r
            LEFT JOIN t.referenceArticles ra
            LEFT JOIN t.utilisateurs u
            WHERE a.type = :id OR cl.type = :id OR c.type = :id OR dl.type = :id OR l.type = :id OR r.type = :id OR ra.type = :id
           "
        )->setParameter('id', $id);

        return $query->getSingleScalarResult();
    }

    public function countByLabelDiff($label, $typeLabel, $category)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT count(t)
            FROM App\Entity\Type t
            WHERE t.label = :label AND t.label != :typeLabel AND t.category = :category"
        )->setParameters([
            'label' => $label,
            'typeLabel' => $typeLabel,
            'category' => $category
        ]);

        return $query->getSingleScalarResult();
    }

    public function findOneByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT t
            FROM App\Entity\Type t
            WHERE LOWER(t.label) = :label
           "
        )->setParameter('label', strtolower($label));

        return $query->getOneOrNullResult();
    }

	/**
	 * @param string $categoryLabel
	 * @param string $typeLabel
	 * @return Type|null
	 * @throws NonUniqueResultException
	 */
    public function findOneByCategoryLabelAndLabel($categoryLabel, $typeLabel)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT t
            FROM App\Entity\Type t
            JOIN t.category c
            WHERE t.label = :typeLabel
            AND c.label = :categoryLabel
           "
		)->setParameters([
			'typeLabel' => $typeLabel,
			'categoryLabel' => $categoryLabel
		]);

		return $query->getOneOrNullResult();
	}

}
