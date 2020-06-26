<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ChampLibre;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Litige;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
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

    public function isTypeUsed($typeId): bool
    {
        $entityManager = $this->getEntityManager();

        $tableConfig = [
            ['class' => Article::class, 'where' => 'item.type = :id'],
            ['class' => ChampLibre::class, 'where' => 'item.type = :id'],
            ['class' => Collecte::class, 'where' => 'item.type = :id'],
            ['class' => Demande::class, 'where' => 'item.type = :id'],
            ['class' => Litige::class, 'where' => 'item.type = :id'],
            ['class' => Reception::class, 'where' => 'item.type = :id'],
            ['class' => ReferenceArticle::class, 'where' => 'item.type = :id'],
            ['class' => Utilisateur::class, 'where' => 'type = :id', 'join' => ['item.types', 'type']]
        ];

        $resultsCount = array_map(function (array $table) use ($entityManager, $typeId) {
            $queryBuilder = $entityManager->createQueryBuilder()
                ->select('COUNT(item)')
                ->from($table['class'], 'item');

            if (isset($table['join'])) {
                $queryBuilder->join($table['join'][0], $table['join'][1]);
            }

            return $queryBuilder
                ->where($table['where'])
                ->setParameter(':id', $typeId)
                ->getQuery()
                ->getSingleScalarResult();
        }, $tableConfig);

        return count(array_filter($resultsCount, function($count) {
            return ((int) $count) > 0;
        })) > 0;
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
