<?php

namespace App\Repository;

use App\Entity\ChampLibre;
use App\Entity\Type;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method ChampLibre|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChampLibre|null findOneBy(array $criteria, array $orderBy = null)
 * @method ChampLibre[]    findAll()
 * @method ChampLibre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChampLibreRepository extends EntityRepository
{

    public function getByTypeAndRequiredCreate($type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id
            FROM App\Entity\ChampLibre c
            WHERE c.type = :type AND c.requiredCreate = TRUE"
        )->setParameter('type', $type);;
        return $query->getResult();
    }

    public function getByTypeAndRequiredEdit($type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id
            FROM App\Entity\ChampLibre c
            WHERE c.type = :type AND c.requiredEdit = TRUE"
        )->setParameter('type', $type);;
        return $query->getResult();
    }

    public function getLabelAndIdAndTypage()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id, c.typage
            FROM App\Entity\ChampLibre c
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
            FROM App\Entity\ChampLibre cl
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
	 * @return ChampLibre|null
	 * @throws NonUniqueResultException
	 */
    public function findOneByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT cl
            FROM App\Entity\ChampLibre cl
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
            FROM App\Entity\ChampLibre cl
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
            FROM App\Entity\ChampLibre cl
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
			"DELETE FROM App\Entity\ChampLibre cl
            WHERE cl.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    public function countByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(cl)
            FROM App\Entity\ChampLibre cl
            WHERE cl.label LIKE :label
           "
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param Type $type
	 * @param string $categorieCLLabel
	 * @return ChampLibre[]
	 */
	public function findByTypeAndCategorieCLLabel($type, $categorieCLLabel)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT c
            FROM App\Entity\ChampLibre c
            JOIN c.categorieCL ccl
            WHERE c.type = :type AND ccl.label = :categorieCLLabel"
		)->setParameters(
			[
				'type' => $type,
				'categorieCLLabel' => $categorieCLLabel,
			]
		);;
		return $query->execute();
	}

    /**
     * @param Type $type
     * @param string $categorieCLLabel
     * @param bool $creation
     * @return ChampLibre[]
     */
	public function getMandatoryByTypeAndCategorieCLLabel($type, $categorieCLLabel, $creation = true)
	{
		$qb = $this->createQueryBuilder('c')
            ->join('c.categorieCL', 'ccl')
            ->join('c.type', 't')
            ->where('t.id = :type AND ccl.label = :categorieCLLabel')
            ->setParameters([
                'type' => $type->getId(),
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
	 * @return ChampLibre[]
	 */
    public function findByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\ChampLibre c
            WHERE c.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

	/**
	 * @param string[] $categoryTypeLabels
	 * @return ChampLibre[]
	 */
	public function findByCategoryTypeLabels($categoryTypeLabels)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT c
            FROM App\Entity\ChampLibre c
            JOIN c.type t
            JOIN t.category cat
            WHERE cat.label in (:categoryTypeLabels)"
		)->setParameter('categoryTypeLabels', $categoryTypeLabels, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

	/**
	 * @param string $categoryCL
	 * @param string $label
	 * @return ChampLibre|null
	 * @throws NonUniqueResultException
	 */
	public function findOneByCategoryCLAndLabel($categoryCL, $label)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT cl
            FROM App\Entity\ChampLibre cl
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
            "DELETE FROM App\Entity\ChampLibre cl
            WHERE cl.label LIKE " . $label);

        return $query->execute();
    }

    public function getIdAndElementsWithMachine()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.id, c.elements
	        FROM App\Entity\ChampLibre c
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
			FROM App\Entity\ChampLibre cl
			JOIN cl.categorieCL cat
			WHERE cat.label = :categoryCL")
			->setParameter('categoryCL', $categoryCL);

		return $query->execute();
	}
}
