<?php

namespace App\Repository\Fields;

use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldStandard;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method FixedFieldStandard|null find($id, $lockMode = null, $lockVersion = null)
 * @method FixedFieldStandard|null findOneBy(array $criteria, array $orderBy = null)
 * @method FixedFieldStandard[]    findAll()
 * @method FixedFieldStandard[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FixedFieldStandardRepository extends EntityRepository
{

    function getByEntity(string $entity): array {
        $fields = $this->createQueryBuilder("fieldsParam")
            ->andWhere("fieldsParam.entityCode = :entity")
            ->setParameter("entity", $entity)
            ->getQuery()
            ->getResult();

        return Stream::from($fields)
            ->keymap(fn(FixedFieldStandard $field) => [$field->getFieldCode(), [
                "requiredCreate" => $field->isRequiredCreate(),
                "requiredEdit" => $field->isRequiredEdit(),
                "displayedCreate" => $field->isDisplayedCreate(),
                "displayedEdit" =>$field->isDisplayedEdit(),
                "displayedFilters" => $field->isDisplayedFilters(),
                "keptInMemory" => $field->isKeptInMemory(),
            ]])
            ->toArray();
    }

    function getByEntityForExport(string $entity): array {
        return $this->createQueryBuilder("fieldsParam")
            ->andWhere("fieldsParam.entityCode = :entity")
            ->setParameter("entity", $entity)
            ->getQuery()
            ->getResult();
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
                FROM App\Entity\Fields\FixedFieldStandard f
                WHERE f.entityCode = :entity AND f.displayedCreate = 0 AND f.displayedEdit = 0"
            )
            ->setParameter('entity', $entity);

		return array_column($query->execute(), 'fieldCode');
	}

    /**
     * @param string[] $fieldCodes
     * @return FixedFieldStandard[]
     */
    function findByEntityCode(string $entityCode, array $fieldCodes = []): array {
        $queryBuilder = $this->createQueryBuilder("fixed_field");

        if (!empty($fieldCodes)) {
            $queryBuilder
                ->andWhere("fixed_field.fieldCode IN (:fieldCodes)")
                ->setParameter('fieldCodes', $fieldCodes);
        }

        return $queryBuilder
            ->andWhere("fixed_field.entityCode = :entityCode")
            ->orderBy("fixed_field.fieldLabel", "ASC")
            ->setParameter("entityCode", $entityCode)
            ->getQuery()
            ->getResult();
    }

    public function findOneByEntityAndCode(string $entity, string $field): ?FixedFieldStandard {
        return $this->createQueryBuilder("f")
            ->where("f.entityCode = :entity")
            ->andWhere("f.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getElements(string $entity, string $field): ?array {
        $result = $this->createQueryBuilder("f")
            ->select("f.elements")
            ->where("f.entityCode = :entity")
            ->andWhere("f.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getResult();

        return $result[0]["elements"] ?? [];
    }

}
