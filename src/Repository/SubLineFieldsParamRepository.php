<?php

namespace App\Repository;

use App\Entity\Fields\SubLineFixedField;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method SubLineFixedField|null find($id, $lockMode = null, $lockVersion = null)
 * @method SubLineFixedField|null findOneBy(array $criteria, array $orderBy = null)
 * @method SubLineFixedField[]    findAll()
 * @method SubLineFixedField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubLineFieldsParamRepository extends EntityRepository
{
    function getByEntity(string $entity): array {
        $fields = $this->createQueryBuilder("subLineFieldsParam")
            ->andWhere("subLineFieldsParam.entityCode = :entity")
            ->setParameter("entity", $entity)
            ->getQuery()
            ->getResult();

        return Stream::from($fields)
            ->keymap(fn(SubLineFixedField $field) => [$field->getFieldCode(), [
                "displayed" => $field->isDisplayed(),
                "displayedUnderCondition" => $field->isDisplayedUnderCondition(),
                "conditionFixedField" => $field->getConditionFixedField(),
                "conditionFixedFieldValue" => $field->getConditionFixedFieldValue(),
                "required" => $field->isRequired(),
            ]])
            ->toArray();
    }

    function findByEntityForEntity(string $entity): array {
        return $this->createQueryBuilder("fixed_field")
            ->where("fixed_field.entityCode = :entity")
            ->orderBy("fixed_field.displayedUnderCondition", "ASC")
            ->setParameter("entity", $entity)
            ->getQuery()
            ->getResult();
    }

    public function findByEntityAndCode(string $entity, string $field): ?SubLineFixedField {
        return $this->createQueryBuilder("subLineFieldsParam")
            ->where("subLineFieldsParam.entityCode = :entity")
            ->andWhere("subLineFieldsParam.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getElements(string $entity, string $field): ?array {
        $result = $this->createQueryBuilder("subLineFieldsParam")
            ->select("subLineFieldsParam.elements")
            ->where("subLineFieldsParam.entityCode = :entity")
            ->andWhere("subLineFieldsParam.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getResult();

        return $result[0]["elements"] ?? [];
    }

}
