<?php

namespace App\Repository\FreeField;

use App\Entity\CategorieCL;
use App\Entity\FreeField\FreeField;
use App\Entity\Type;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use WiiCommon\Helper\Stream;

/**
 * @method FreeField|null find($id, $lockMode = null, $lockVersion = null)
 * @method FreeField|null findOneBy(array $criteria, array $orderBy = null)
 * @method FreeField[]    findAll()
 * @method FreeField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FreeFieldRepository extends EntityRepository {

    public function getByTypeAndRequiredCreate(Type $type) {
        $queryBuilder = $this->createQueryBuilder('free_field');
        return $queryBuilder
            ->select('free_field.label, free_field.id')
            ->join("free_field.freeFieldManagementRules", "free_field_management_rules")
            ->andWhere($queryBuilder->expr()->eq('free_field_management_rules.type', ':type'))
            ->andWhere($queryBuilder->expr()->eq('free_field_management_rules.requiredCreate', true))
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();

    }

    public function getByTypeAndRequiredEdit($type) {
        $queryBuilder = $this->createQueryBuilder('free_field');
        return $queryBuilder
            ->select('free_field.label, free_field.id')
            ->join("free_field.freeFieldManagementRules", "free_field_management_rules")
            ->andWhere($queryBuilder->expr()->eq('free_field_management_rules.type', ':type'))
            ->andWhere($queryBuilder->expr()->eq('free_field_management_rules.requiredEdit', true))
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    public function getByCategoryTypeAndCategoryCL(string $typeCategory, CategorieCL $ffCategory): array {
        return $this->createQueryBuilder("f")
            ->select("f.id AS id")
            ->addSelect("f.label AS label")
            ->addSelect("f.typage AS typage")
            ->join("f.type", "t")
            ->join("t.category", "c")
            ->where("c.label = :type")
            ->andWhere("f.categorieCL = :category")
            ->setParameter("type", $typeCategory)
            ->setParameter("category", $ffCategory)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FreeField[]
     */
    public function findByCategoryTypeAndCategoryCL(string $typeCategory, string $freeFieldCategory): array {
        return $this->createQueryBuilder("free_field")
            ->join("free_field.freeFieldManagementRules", "free_field_management_rules")
            ->join("free_field_management_rules.type", "join_type")
            ->join("join_type.category", "join_type_category")
            ->join("free_field.categorieCL", "join_free_field_category")
            ->andWhere("join_type_category.label = :type")
            ->andWhere("join_free_field_category.label = :category")
            ->setParameter("type", $typeCategory)
            ->setParameter("category", $freeFieldCategory)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $label
     * @return FreeField|null
     * @throws NonUniqueResultException
     */
    public function findOneByLabel($label): ?FreeField {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT cl
            FROM App\Entity\FreeField\FreeField cl
            WHERE cl.label LIKE :label
            "
        )->setParameter('label', $label);
        return $query->getOneOrNullResult();
    }

    public function countByType($typeId) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(cl)
            FROM App\Entity\FreeField\FreeField cl
            WHERE cl.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function countByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(cl)
            FROM App\Entity\FreeField\FreeField cl
            WHERE cl.label LIKE :label
           "
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

    public function findByTypeAndCategorieCLLabel(Type|array $types, string $freeFieldCategoryLabel): mixed {
        $types = is_array($types) ? $types : [$types];
        $typeIds = Stream::from($types)->map(fn(Type $type) => $type->getId())->toArray();

        return $this->createQueryBuilder('free_field')
            ->join("free_field.freeFieldManagementRules", "free_field_management_rules")
            ->join("free_field_management_rules.type", "type")
            ->join('free_field.categorieCL', 'free_field_category')
            ->where('type.id IN (:types)')
            ->andWhere('free_field_category.label = :freeFieldCategoryLabel')
            ->orderBy('free_field.label', 'ASC')
            ->setParameter('types', $typeIds)
            ->setParameter('freeFieldCategoryLabel', $freeFieldCategoryLabel)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Type $type
     * @param string $categorieCLLabel
     * @param bool $creation
     * @return FreeField[]
     */
    public function getMandatoryByTypeAndCategorieCLLabel($type, $categorieCLLabel, $creation = true): array {
        $qb = $this->createQueryBuilder('c')
            ->join('c.categorieCL', 'ccl')
            ->where('c.type = :type AND ccl.label = :categorieCLLabel')
            ->setParameters([
                'type' => $type,
                'categorieCLLabel' => $categorieCLLabel,
            ]);

        if($creation) {
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
    public function findByType($typeId) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\FreeField\FreeField c
            WHERE c.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    /**
     * @param string[] $categoryTypeLabels
     * @return FreeField[]
     */
    public function findByCategoryTypeLabels(array $categoryTypeLabels): array {
        return $this->createQueryBuilder('free_field')
            ->join("free_field.freeFieldManagementRules", "free_field_management_rules")
            ->join('free_field_management_rules.type', 'type')
            ->join('type.category', 'category')
            ->where('category.label IN (:categoryTypeLabels)')
            ->setParameter('categoryTypeLabels', $categoryTypeLabels, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->execute();
    }

    /**
     * @param string[] $categoryCLLabels
     * @return FreeField[]
     */
    public function findByFreeFieldCategoryLabels(array $categoryCLLabels, ?array $typeCategories = []): array {
        $queryBuilder = $this->createQueryBuilder('freeField')
            ->join('freeField.categorieCL', 'categorieCL')
            ->where('categorieCL.label IN (:categoryCLLabels)')
            ->setParameter('categoryCLLabels', $categoryCLLabels, Connection::PARAM_STR_ARRAY);

        if(!empty($typeCategories)) {
            $queryBuilder
                ->join("freeField.type", "join_type")
                ->join("join_type.category", "join_type_category")
                ->andWhere("join_type_category.label IN (:typeCategories)")
                ->setParameter('typeCategories', $typeCategories);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $categoryCL
     * @return array
     */
    public function getLabelAndIdByCategory($categoryCL): array {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT cl.label as value, cl.id as id
			FROM App\Entity\FreeField\FreeField cl
			JOIN cl.categorieCL cat
			WHERE cat.label = :categoryCL")
            ->setParameter('categoryCL', $categoryCL);

        return $query->execute();
    }

    public function findByCategory(string $category): array {
        return $this->createQueryBuilder("free_field")
            ->join("free_field.categorieCL", "category")
            ->andWhere("category.label = :category")
            ->setParameter("category", $category)
            ->getQuery()
            ->getResult();
	}

}
