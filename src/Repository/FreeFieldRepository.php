<?php

namespace App\Repository;

use App\Entity\FreeField;
use App\Entity\Type;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method FreeField|null find($id, $lockMode = null, $lockVersion = null)
 * @method FreeField|null findOneBy(array $criteria, array $orderBy = null)
 * @method FreeField[]    findAll()
 * @method FreeField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FreeFieldRepository extends EntityRepository
{

    public function getByTypeAndRequiredCreate($type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id
            FROM App\Entity\FreeField c
            WHERE c.type = :type AND c.requiredCreate = TRUE"
        )->setParameter('type', $type);;
        return $query->getResult();
    }

    public function getByTypeAndRequiredEdit($type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id
            FROM App\Entity\FreeField c
            WHERE c.type = :type AND c.requiredEdit = TRUE"
        )->setParameter('type', $type);;
        return $query->getResult();
    }

    public function getLabelAndIdAndTypage()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id, c.typage
            FROM App\Entity\FreeField c
            "
        );
        return $query->getResult();
    }

    // pour les colonnes dynamiques
    public function getByCategoryTypeAndCategoryCL($category, $categorieCL)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT cl.label, cl.id, cl.typage
            FROM App\Entity\FreeField cl
            JOIN cl.type t
            JOIN t.category cat
            WHERE cat.label = :category AND cl.categorieCL = :categorie
            "
        )->setParameters(
            [
                'category' => $category,
                'categorie' => $categorieCL
            ]
        );
        return $query->getResult();
    }

	/**
	 * @param string $label
	 * @return FreeField|null
	 * @throws NonUniqueResultException
	 */
    public function findOneByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT cl
            FROM App\Entity\FreeField cl
            WHERE cl.label LIKE :label
            "
        )->setParameter('label', $label);
        return $query->getOneOrNullResult();
    }

    // pour les colonnes dynamiques
    public function getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, $type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT cl.label, cl.id, cl.typage
            FROM App\Entity\FreeField cl
            JOIN cl.type t
            JOIN t.category cat
            WHERE cat.label = :category AND cl.categorieCL = :categorie AND cl.typage = :text
            "
        )->setParameters(
            [
                'category' => $category,
                'categorie' => $categorieCL,
                'text' => $type
            ]
        );
        return $query->getResult();
    }

    public function countByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(cl)
            FROM App\Entity\FreeField cl
            WHERE cl.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function deleteByType($typeId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "DELETE FROM App\Entity\FreeField cl
            WHERE cl.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    public function countByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(cl)
            FROM App\Entity\FreeField cl
            WHERE cl.label LIKE :label
           "
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

    /**
     * @param Type|Type[] $types
     * @param string $categorieCLLabel
     * @return FreeField[]
     */
	public function findByTypeAndCategorieCLLabel($types, $categorieCLLabel)
	{
        if (!is_array($types)){
            $types = [$types];
        }
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
            "SELECT champLibre
            FROM App\Entity\FreeField champLibre
            JOIN champLibre.categorieCL categorieChampLibre
            JOIN champLibre.type type
            WHERE type.id IN (:types)  AND categorieChampLibre.label = :categorieCLLabel "
        )
            ->setParameter(
                'types',
                array_map( function (Type $type) {
                    return $type->getId();
                } , $types),
                Connection::PARAM_STR_ARRAY
            )
            ->setParameter('categorieCLLabel', $categorieCLLabel);

        return $query->execute();
	}

    /**
     * @param Type $type
     * @param string $categorieCLLabel
     * @param bool $creation
     * @return FreeField[]
     */
	public function getMandatoryByTypeAndCategorieCLLabel($type, $categorieCLLabel, $creation = true)
	{
		$qb = $this->createQueryBuilder('c')
            ->join('c.categorieCL', 'ccl')
            ->where('c.type = :type AND ccl.label = :categorieCLLabel')
            ->setParameters([
                'type' => $type,
                'categorieCLLabel' => $categorieCLLabel,
            ]);

		if ($creation) {
            $qb->andWhere('c.requiredCreate = 1');
        } else {
            $qb->andWhere('c.requiredEdit = 1');
        }

		return $qb->getQuery()->getResult();
	}

	/**
	 * @param int|Type $typeId
	 * @return FreeField[]
	 */
    public function findByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\FreeField c
            WHERE c.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

	/**
	 * @param string[] $categoryTypeLabels
	 * @return FreeField[]
	 */
	public function findByCategoryTypeLabels($categoryTypeLabels)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\FreeField c
            JOIN c.type t
            JOIN t.category cat
            WHERE cat.label in (:categoryTypeLabels)"
		)->setParameter('categoryTypeLabels', $categoryTypeLabels, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

    /**
     * @param string[] $categoryCLLabels
     * @return FreeField[]
     */
	public function findByFreeFieldCategoryLabels(array $categoryCLLabels)
	{
		return $this->createQueryBuilder('freeField')
            ->join('freeField.categorieCL', 'categorieCL')
            ->where('categorieCL.label IN (:categoryCLLabels)')
            ->setParameter('categoryCLLabels', $categoryCLLabels, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->getResult();
	}

	/**
	 * @param string $categoryCL
	 * @param string $label
	 * @return FreeField|null
	 * @throws NonUniqueResultException
	 */
	public function findOneByCategoryCLAndLabel($categoryCL, $label)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
            "SELECT cl
            FROM App\Entity\FreeField cl
            JOIN cl.categorieCL ccl
            WHERE ccl.label = :categoryCL
            AND cl.label = :label"
		)->setParameters([
			'categoryCL' => $categoryCL,
			'label' => $label
			]);

		return $query->getOneOrNullResult();
	}

	public function deleteByLabel($label){
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "DELETE FROM App\Entity\FreeField cl
            WHERE cl.label LIKE " . $label);

        return $query->execute();
    }

    public function getIdAndElementsWithMachine()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.id, c.elements
	        FROM App\Entity\FreeField c
	        WHERE c.label LIKE '%machine%'"
        );
        return $query->execute();
    }

	/**
	 * @param string $categoryCL
	 * @return array
	 */
    public function getLabelAndIdByCategory($categoryCL)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
            "SELECT cl.label as value, cl.id as id
			FROM App\Entity\FreeField cl
			JOIN cl.categorieCL cat
			WHERE cat.label = :categoryCL")
			->setParameter('categoryCL', $categoryCL);

		return $query->execute();
	}
}
