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

	/**
	 * @param $entity
	 * @return FieldsParam[]
	 */
    function findByEntityForEntity($entity) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT f
            FROM App\Entity\FieldsParam f
            WHERE f.entityCode = :entity"
        )->setParameter('entity', $entity);

        return $query->execute();
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
