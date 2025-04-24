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
     * @return FreeField[]
     * @param array<string> $typeCategories
     * @param array<string> $freeFieldCategories
     */
    public function findByCategoriesTypeAndCategoriesCL(array $typeCategories, array $freeFieldCategories): array {
        return $this->createQueryBuilder("free_field")
            ->join("free_field.freeFieldManagementRules", "free_field_management_rules")
            ->join("free_field_management_rules.type", "join_type")
            ->join("join_type.category", "join_type_category")
            ->join("free_field.categorieCL", "join_free_field_category")
            ->andWhere("join_type_category.label IN (:type)")
            ->andWhere("join_free_field_category.label IN (:category)")
            ->setParameter("type", $typeCategories)
            ->setParameter("category", $freeFieldCategories)
            ->getQuery()
            ->getResult();
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
     * @param int|Type $typeId
     * @return FreeField[]
     */
    public function findByType($typeId): array {
        $queryBuilder = $this->createQueryBuilder('free_field');
        return $queryBuilder
            ->join("free_field.freeFieldManagementRules", "free_field_management_rules")
            ->andWhere($queryBuilder->expr()->eq('free_field_management_rules.type', ':typeId'))
            ->setParameter('typeId', $typeId)
            ->getQuery()
            ->getResult();
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
                ->join("categorieCL.categoryType", "join_type_category")
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
    public function getLabelAndIdByCategory(string $categoryCL): array {
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

    public function getForSelect(string $term, string $category): array {
        return $this->createQueryBuilder("free_field")
            ->select("free_field.id AS id")
            ->addSelect("free_field.label AS text")
            ->join("free_field.categorieCL", "category_ff")
            ->join("category_ff.categoryType", "category_type")
            ->andWhere("free_field.label LIKE :term")
            ->andWhere("category_type.label = :category")
            ->setParameter("term", "%$term%")
            ->setParameter("category", $category)
            ->getQuery()
            ->getResult();
    }

}
