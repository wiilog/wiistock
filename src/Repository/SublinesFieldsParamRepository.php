<?php

namespace App\Repository;

use App\Entity\SublinesFieldsParam;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method SublinesFieldsParam|null find($id, $lockMode = null, $lockVersion = null)
 * @method SublinesFieldsParam|null findOneBy(array $criteria, array $orderBy = null)
 * @method SublinesFieldsParam[]    findAll()
 * @method SublinesFieldsParam[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SublinesFieldsParamRepository extends EntityRepository
{
    function getByEntity(string $entity): array {
        $fields = $this->createQueryBuilder("sublinesFieldsParam")
            ->andWhere("sublinesFieldsParam.entityCode = :entity")
            ->setParameter("entity", $entity)
            ->getQuery()
            ->getResult();

        return Stream::from($fields)
            ->keymap(fn(SublinesFieldsParam $field) => [$field->getFieldCode(), [
                "displayed" =>$field->isDisplayed(),
                "displayedUnderCondition" =>$field->isDisplayedUnderCondition(),
                "conditionFixedField" =>$field->getConditionFixedField(),
                "conditionFixedFieldValue" =>$field->getConditionFixedFieldValue(),
                "required" => $field->isRequired(),
            ]])
            ->toArray();
    }

    function findByEntityForEntity(string $entity): array {
        return $this->createQueryBuilder("fixed_field")
            ->where("fixed_field.entityCode = :entity")
            ->orderBy("fixed_field.fieldLabel", "ASC")
            ->setParameter("entity", $entity)
            ->getQuery()
            ->getResult();
    }

    public function findByEntityAndCode(string $entity, string $field): ?SublinesFieldsParam {
        return $this->createQueryBuilder("sublinesFieldsParam")
            ->where("sublinesFieldsParam.entityCode = :entity")
            ->andWhere("sublinesFieldsParam.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getElements(string $entity, string $field): ?array {
        $result = $this->createQueryBuilder("sublinesFieldsParam")
            ->select("sublinesFieldsParam.elements")
            ->where("sublinesFieldsParam.entityCode = :entity")
            ->andWhere("sublinesFieldsParam.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getResult();

        return $result[0]["elements"] ?? [];
    }

}
