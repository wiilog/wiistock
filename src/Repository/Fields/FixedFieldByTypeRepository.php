<?php

namespace App\Repository\Fields;

use App\Entity\Fields\FixedFieldByType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use WiiCommon\Helper\Stream;

/**
 * @extends EntityRepository<FixedFieldByType>
 *
 * @method FixedFieldByType|null find($id, $lockMode = null, $lockVersion = null)
 * @method FixedFieldByType|null findOneBy(array $criteria, array $orderBy = null)
 * @method FixedFieldByType[]    findAll()
 * @method FixedFieldByType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FixedFieldByTypeRepository extends EntityRepository {
    function getByEntity(string $entity, ?array $attributes = null): array {
        $delimiter = ' ';
        $attributes = $attributes ?: FixedFieldByType::ATTRIBUTES;

        $subQuery = function (string $field) use ($delimiter, $entity): string {
            return $this->createQueryBuilder("fixedFieldByType_$field")
                ->select("GROUP_CONCAT(DISTINCT join_$field.id SEPARATOR '$delimiter')")
                ->leftJoin("fixedFieldByType_$field.$field", "join_$field", Join::WITH, "fixedFieldByType_$field = fixedFieldByType")
                ->getQuery()
                ->getDQL();
        };

        $qb = $this->createQueryBuilder("fixedFieldByType")
            ->select("fixedFieldByType.fieldCode AS fieldCode")
            ->andWhere("fixedFieldByType.entityCode = :entity")
            ->setParameter("entity", $entity);

        foreach ($attributes as $field) {
            $qb->addselect("({$subQuery($field)}) AS $field");
        }

        $field = $qb
            ->getQuery()
            ->getResult();

        return Stream::from($field)
            ->keymap(static fn(array $params) => [
                $params['fieldCode'],
                Stream::from($params)
                    ->filter(fn($value, string $key) => $key !== 'fieldCode' && $value !== null)
                    // ->map(static fn(string $value) => explode($delimiter, $value))
                    ->toArray()
            ])
            ->toArray();
    }

    public function getElements(string $entity, string $field): ?array {
        $result = $this->createQueryBuilder("fixedFieldByType")
            ->select("fixedFieldByType.elements")
            ->where("fixedFieldByType.entityCode = :entity")
            ->andWhere("fixedFieldByType.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getResult();

        return $result[0]["elements"] ?? [];
    }

    function findByEntityCode(string $entityCode, array $fieldCodes = []): array {
        $queryBuilder = $this->createQueryBuilder("fixedFieldByType");

        if (!empty($fieldCodes)) {
            $queryBuilder
                ->andWhere("fixedFieldByType.fieldCode IN (:fieldCodes)")
                ->setParameter('fieldCodes', $fieldCodes);
        }

        return $queryBuilder
            ->andWhere("fixedFieldByType.entityCode = :entityCode")
            ->orderBy("fixedFieldByType.fieldLabel", "ASC")
            ->setParameter("entityCode", $entityCode)
            ->getQuery()
            ->getResult();
    }
}
