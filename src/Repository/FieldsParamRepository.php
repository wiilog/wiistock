<?php

namespace App\Repository;

use App\Entity\FieldsParam;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method FieldsParam|null find($id, $lockMode = null, $lockVersion = null)
 * @method FieldsParam|null findOneBy(array $criteria, array $orderBy = null)
 * @method FieldsParam[]    findAll()
 * @method FieldsParam[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FieldsParamRepository extends EntityRepository
{

    function getByEntity(string $entity): array {
        $fields = $this->createQueryBuilder("fieldsParam")
            ->andWhere("fieldsParam.entityCode = :entity")
            ->setParameter("entity", $entity)
            ->getQuery()
            ->getResult();

        return Stream::from($fields)
            ->keymap(fn(FieldsParam $field) => [$field->getFieldCode(), [
                "requiredCreate" => $field->isRequiredCreate(),
                "requiredEdit" => $field->isRequiredEdit(),
                "displayedCreate" => $field->isDisplayedCreate(),
                "displayedEdit" =>$field->isDisplayedEdit(),
                "displayedFilters" => $field->isDisplayedFilters(),
            ]])
            ->toArray();
    }

    /**
     * @param string $entity
     * @return array
     */
    function getHiddenByEntity($entity): array {
        $em = $this->getEntityManager();
        $query = $em
            ->createQuery(
                "SELECT f.fieldCode
                FROM App\Entity\FieldsParam f
                WHERE f.entityCode = :entity AND f.displayedCreate = 0 AND f.displayedEdit = 0"
            )
            ->setParameter('entity', $entity);

		return array_column($query->execute(), 'fieldCode');
	}

    function findByEntityForEntity(string $entity): array {
        return $this->createQueryBuilder("fixed_field")
            ->where("fixed_field.entityCode = :entity")
            ->orderBy("fixed_field.fieldLabel", "ASC")
            ->setParameter("entity", $entity)
            ->getQuery()
            ->getResult();
    }

    public function findByEntityAndCode(string $entity, string $field): ?FieldsParam {
        return $this->createQueryBuilder("f")
            ->where("f.entityCode = :entity")
            ->andWhere("f.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getElements(string $entity, string $field): ?array {
        $queryBuilder = $this->createQueryBuilder("f")
            ->select("f.elements")
            ->where("f.entityCode = :entity")
            ->andWhere("f.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return $res[0]["elements"] ?? [];
    }

}
