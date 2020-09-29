<?php

namespace App\Repository;

use App\Entity\FieldsParam;
use Doctrine\ORM\EntityRepository;

/**
 * @method FieldsParam|null find($id, $lockMode = null, $lockVersion = null)
 * @method FieldsParam|null findOneBy(array $criteria, array $orderBy = null)
 * @method FieldsParam[]    findAll()
 * @method FieldsParam[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FieldsParamRepository extends EntityRepository
{
    /**
     * @param $entity
     * @return array [fieldCode => ['mustToCreate' => boolean, 'mustToModify' => boolean, 'displayedFormsCreate' => boolean, 'displayedFormsEdit' => boolean, 'displayedFilters' => boolean]]
     */
    function getByEntity($entity): array {
        $em = $this->getEntityManager();
        $query = $em
            ->createQuery(
                "SELECT f.fieldCode, f.fieldLabel, f.mustToCreate, f.mustToModify, f.displayedFormsCreate, f.displayedFormsEdit, f.displayedFilters
                FROM App\Entity\FieldsParam f
                WHERE f.entityCode = :entity"
            )
            ->setParameter('entity', $entity);
        $result = $query->execute();
        return array_reduce(
            $result,
            function (array $acc, $field) {
                $acc[$field['fieldCode']] = [
                    'mustToCreate' => $field['mustToCreate'],
                    'mustToModify' => $field['mustToModify'],
                    'displayedFormsCreate' => $field['displayedFormsCreate'],
                    'displayedFormsEdit' => $field['displayedFormsEdit'],
                    'displayedFilters' => $field['displayedFilters']
                ];
                return $acc;
            },
            []);
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
                WHERE f.entityCode = :entity AND f.displayedFormsCreate = 0 AND f.displayedFormsEdit = 0"
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

    public function getElements(string $entity, string $field): ?array {
        return $this->createQueryBuilder("f")
            ->select("f.elements")
            ->where("f.entityCode = :entity")
            ->andWhere("f.fieldCode = :field")
            ->setParameter("entity", $entity)
            ->setParameter("field", $field)
            ->getQuery()
            ->getSingleResult()["elements"];
    }

}
